package bj.vivi.app.data

import android.content.Context
import com.google.firebase.FirebaseApp
import com.google.firebase.messaging.FirebaseMessaging
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume

/**
 * Notifications push Firebase (FCM). Inactif tant que app/google-services.json n'est pas ajouté au projet
 * (Firebase non initialisé) ou que la plateforme n'a pas Firebase (configuration.push = false) :
 * la vérification régulière d'[Alertes] prend alors le relais.
 *
 * Jeton envoyé à POST /appareils après la connexion et à chaque nouveau jeton ; retiré à la déconnexion
 * (POST /auth/deconnexion { jeton_push }).
 */
object Push {
    private const val PREFS = "vivi_push"
    private const val CLE_JETON = "jeton"
    private const val CLE_ACTIF = "actif"
    private val portee = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    fun disponible(context: Context): Boolean = FirebaseApp.getApps(context).isNotEmpty()

    /** Push effectif sur ce téléphone : jeton enregistré et Firebase configuré sur la plateforme. */
    fun actif(context: Context): Boolean =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getBoolean(CLE_ACTIF, false)

    /** Dernier jeton connu (pour la déconnexion). */
    fun jeton(context: Context): String? =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(CLE_JETON, null)

    /** À appeler une fois connecté (et au démarrage) : récupère le jeton Firebase et l'enregistre. */
    fun enregistrer(context: Context) {
        val appli = context.applicationContext
        if (!disponible(appli) || Reseau.session.jeton == null) return
        portee.launch {
            val jeton = suspendCancellableCoroutine<String?> { suite ->
                FirebaseMessaging.getInstance().token.addOnCompleteListener { t -> suite.resume(if (t.isSuccessful) t.result else null) }
            } ?: return@launch
            envoyer(appli, jeton)
        }
    }

    internal suspend fun envoyer(context: Context, jeton: String) {
        if (Reseau.session.jeton == null) {
            // Pas connecté : gardé pour la prochaine connexion
            context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putString(CLE_JETON, jeton).apply()
            return
        }
        appel { enregistrerAppareil(corps("jeton" to jeton, "plateforme" to "android", "nom" to Reseau.nomAppareil)) }
            .onSuccess {
                context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit()
                    .putString(CLE_JETON, jeton).putBoolean(CLE_ACTIF, it.data.pushActif).apply()
            }
    }

    /** Déconnexion : le push s'arrête pour ce compte. */
    fun oublier(context: Context) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putBoolean(CLE_ACTIF, false).apply()
    }
}

/** Réception des push : nouveau jeton, et message reçu application ouverte (sinon Android l'affiche seul). */
class ServicePush : FirebaseMessagingService() {
    override fun onNewToken(token: String) {
        CoroutineScope(Dispatchers.IO).launch { Push.envoyer(applicationContext, token) }
    }

    override fun onMessageReceived(message: RemoteMessage) {
        val n = message.notification
        Alertes.montrerPush(
            applicationContext,
            id = message.data["notification_id"],
            titre = n?.title ?: "VIVI",
            texte = n?.body.orEmpty(),
            donnees = message.data,
        )
    }
}
