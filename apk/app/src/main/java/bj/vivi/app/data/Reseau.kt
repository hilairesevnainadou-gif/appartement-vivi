package bj.vivi.app.data

import android.content.Context
import android.content.SharedPreferences
import android.os.Build
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import bj.vivi.app.BuildConfig
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import okhttp3.Interceptor
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.HttpException
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.io.IOException
import java.util.concurrent.TimeUnit

val JsonVivi = Json {
    ignoreUnknownKeys = true
    coerceInputValues = true
    explicitNulls = false
    isLenient = true
}

/**
 * Jeton d'accès (Bearer) gardé dans un stockage chiffré par le Keystore Android,
 * comme le demande la documentation de l'API. Jamais sauvegardé dans le cloud (allowBackup=false).
 */
class Session(context: Context) {
    private val prefs: SharedPreferences = try {
        EncryptedSharedPreferences.create(
            context, "vivi_session",
            MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build(),
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
        )
    } catch (e: Exception) {
        // Clé du Keystore perdue (restauration, changement de verrouillage) : on repart d'une session vide
        context.deleteSharedPreferences("vivi_session")
        EncryptedSharedPreferences.create(
            context, "vivi_session",
            MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build(),
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
        )
    }

    private val _utilisateur = MutableStateFlow(lireUtilisateur())
    /** Compte connecté (null : visiteur). Les écrans observent ce flux pour basculer client / pro. */
    val utilisateur: StateFlow<Utilisateur?> = _utilisateur

    val jeton: String? get() = prefs.getString(CLE_JETON, null)

    fun ouvrir(connexion: Connexion) {
        prefs.edit().putString(CLE_JETON, connexion.jeton)
            .putString(CLE_UTILISATEUR, JsonVivi.encodeToString(Utilisateur.serializer(), connexion.utilisateur)).apply()
        _utilisateur.value = connexion.utilisateur
    }

    fun mettreAJour(utilisateur: Utilisateur) {
        prefs.edit().putString(CLE_UTILISATEUR, JsonVivi.encodeToString(Utilisateur.serializer(), utilisateur)).apply()
        _utilisateur.value = utilisateur
    }

    fun fermer() {
        prefs.edit().clear().apply()
        _utilisateur.value = null
        Alertes.oublier(appli)   // notifications du compte retirées du téléphone
    }

    private val appli: Context = context.applicationContext

    private fun lireUtilisateur(): Utilisateur? {
        if (prefs.getString(CLE_JETON, null) == null) return null
        return prefs.getString(CLE_UTILISATEUR, null)?.let { runCatching { JsonVivi.decodeFromString(Utilisateur.serializer(), it) }.getOrNull() }
    }

    private companion object {
        const val CLE_JETON = "jeton"
        const val CLE_UTILISATEUR = "utilisateur"
    }
}

/** Client HTTP : en-têtes JSON, jeton Bearer, et retour à la connexion sur 401 (jeton expiré ou révoqué). */
object Reseau {
    /**
     * Session chiffrée : son ouverture (Keystore, ~0,5 s sur un téléphone d'entrée de gamme) est lancée
     * en arrière-plan dès le démarrage, en parallèle de la création de l'écran. Le premier accès attend
     * seulement le temps qui reste.
     */
    private lateinit var sessionEnPreparation: java.util.concurrent.FutureTask<Session>
    val session: Session get() = sessionEnPreparation.get()
    lateinit var api: ViviApi
        private set

    val nomAppareil: String = "${Build.MANUFACTURER} ${Build.MODEL}".trim().take(100)

    fun initialiser(context: Context) {
        val app = context.applicationContext
        sessionEnPreparation = java.util.concurrent.FutureTask { Session(app) }
        Thread(sessionEnPreparation, "vivi-session").apply { priority = Thread.MAX_PRIORITY }.start()
        Serveur.initialiser(context)
        val enTetes = Interceptor { chaine ->
            val requete = chaine.request().newBuilder()
                .header("Accept", "application/json")
                .header("Accept-Language", "fr")
                .header("X-App-Version", BuildConfig.VERSION_NAME)
                .apply { session.jeton?.let { header("Authorization", "Bearer $it") } }
                .build()
            val reponse = chaine.proceed(requete)
            if (reponse.code == 401 && requete.header("Authorization") != null) session.fermer()
            reponse
        }
        val client = OkHttpClient.Builder()
            .connectTimeout(if (Serveur.rechercheActive) 6L else 15L, TimeUnit.SECONDS)   // serveur local : bascule vite vers la recherche du serveur
            .readTimeout(30, TimeUnit.SECONDS)
            .addInterceptor(enTetes)
            .addInterceptor(Serveur.intercepteur)   // test : suit la plateforme si l'IP du PC change
            .apply {
                if (BuildConfig.DEBUG) addInterceptor(HttpLoggingInterceptor().setLevel(HttpLoggingInterceptor.Level.BASIC))
            }
            .build()
        api = Retrofit.Builder()
            .baseUrl(BuildConfig.API_URL)
            .client(client)
            .addConverterFactory(JsonVivi.asConverterFactory("application/json".toMediaType()))
            .build()
            .create(ViviApi::class.java)
    }
}

/* ---------- Erreurs ---------- */

/**
 * Erreur présentable à la personne. Les messages de l'API sont en français et s'affichent tels quels.
 * [champs] : erreurs de validation (422) par champ, par exemple « identifiant » ou « reservation ».
 */
class ErreurVivi(
    val code: Int,
    override val message: String,
    val champs: Map<String, String> = emptyMap(),
    val corps: JsonObject? = null,
) : Exception(message) {
    fun champ(nom: String): String? = champs[nom]
    val horsLigne get() = code == 0
}

fun Throwable.enErreurVivi(): ErreurVivi = when (this) {
    is ErreurVivi -> this
    is HttpException -> {
        val brut = runCatching { response()?.errorBody()?.string() }.getOrNull()
        val corps = brut?.let { runCatching { JsonVivi.parseToJsonElement(it).jsonObject }.getOrNull() }
        val champs = (corps?.get("errors") as? JsonObject)?.mapValues { (_, v) ->
            runCatching { v.jsonArray.first().jsonPrimitive.content }.getOrDefault("")
        } ?: emptyMap()
        val message = champs.values.firstOrNull { it.isNotBlank() }
            ?: (corps?.get("message") as? JsonPrimitive)?.content?.takeIf { it.isNotBlank() }
            ?: when (code()) {
                401 -> "Connectez-vous pour continuer."
                403 -> "Cet espace n’est pas accessible avec votre compte."
                404 -> "Introuvable."
                429 -> "Trop de demandes. Patientez un instant puis réessayez."
                in 500..599 -> "Le service VIVI rencontre un problème. Réessayez dans un instant."
                else -> "Une erreur est survenue (${code()})."
            }
        ErreurVivi(code(), message, champs, corps)
    }
    is IOException -> ErreurVivi(0, "Pas de connexion au serveur VIVI. Vérifiez votre accès à Internet, puis réessayez.")
    else -> ErreurVivi(-1, "Une erreur inattendue est survenue. Réessayez.")
}

/** Exécute un appel d'API et renvoie un Result dont l'échec est toujours une ErreurVivi. */
suspend fun <T> appel(bloc: suspend ViviApi.() -> T): Result<T> = try {
    Result.success(Reseau.api.bloc())
} catch (e: CancellationException) {
    throw e
} catch (e: Throwable) {
    Result.failure(e.enErreurVivi())
}

/* ---------- Corps JSON ---------- */

/** Construit un corps JSON : corps("code" to "123456", "services" to mapOf(12 to 2)). Les valeurs nulles sont omises. */
fun corps(vararg paires: Pair<String, Any?>): JsonObject =
    JsonObject(paires.filter { it.second != null }.associate { (k, v) -> k to versJson(v) })

private fun versJson(v: Any?): JsonElement = when (v) {
    null -> JsonNull
    is JsonElement -> v
    is String -> JsonPrimitive(v)
    is Number -> JsonPrimitive(v)
    is Boolean -> JsonPrimitive(v)
    is Map<*, *> -> JsonObject(v.entries.associate { (k, x) -> k.toString() to versJson(x) })
    is Iterable<*> -> JsonArray(v.map { versJson(it) })
    else -> JsonPrimitive(v.toString())
}
