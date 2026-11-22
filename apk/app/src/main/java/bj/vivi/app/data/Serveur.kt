package bj.vivi.app.data

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import bj.vivi.app.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.sync.Semaphore
import kotlinx.coroutines.sync.withPermit
import kotlinx.coroutines.withContext
import okhttp3.HttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.Request
import java.io.IOException
import java.net.ConnectException
import java.net.Inet4Address
import java.net.NoRouteToHostException
import java.net.SocketTimeoutException
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicReference

/**
 * Version de test seulement : retrouve la plateforme sur le Wi-Fi même si l'adresse IP du PC change.
 *
 * L'APK est construit avec une adresse (viviApiUrl, ex. http://192.168.1.189:8049/api/v1/). Si le serveur
 * ne répond plus à cette adresse (connexion refusée ou impossible : la requête n'est jamais partie), le
 * téléphone interroge les 254 adresses de son réseau Wi-Fi sur le même port, garde la première qui répond
 * comme la plateforme VIVI (GET /configuration), et rejoue la requête. L'adresse trouvée est retenue.
 * Avec une adresse HTTPS (environnement de test en ligne, production), rien de tout cela ne s’applique.
 */
object Serveur {
    private const val PREFS = "vivi_serveur"
    private const val CLE_API = "api"

    private val origine: HttpUrl = BuildConfig.API_URL.toHttpUrl()
    /** Recherche sur le Wi-Fi : seulement pour un serveur local (HTTP). */
    val rechercheActive = BuildConfig.DEBUG && !origine.isHttps
    private val actuelle = AtomicReference(origine)
    private var contexte: Context? = null
    private val verrou = Any()
    @Volatile private var derniereRecherche = 0L

    /** Adresse de l'API utilisée en ce moment (celle de l'APK ou celle trouvée sur le réseau). */
    val api: HttpUrl get() = actuelle.get()

    fun initialiser(context: Context) {
        contexte = context.applicationContext
        if (!BuildConfig.DEBUG) return
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        if (!rechercheActive) {
            // Adresse Wi-Fi retenue par une version précédente de l’APK : elle ne vaut plus rien
            prefs.edit().remove(CLE_API).apply()
            return
        }
        prefs.getString(CLE_API, null)?.toHttpUrlOrNull()?.let { actuelle.set(it) }
    }

    /** Intercepteur : redirige vers l'adresse actuelle, et relance la recherche si le serveur est injoignable. */
    val intercepteur = Interceptor { chaine ->
        val requete = chaine.request()
        if (!rechercheActive || !estApi(requete.url)) return@Interceptor chaine.proceed(requete)
        val base = actuelle.get()
        try {
            chaine.proceed(rediriger(requete, base))
        } catch (e: IOException) {
            if (!injoignable(e)) throw e
            val trouvee = synchronized(verrou) { rechercher(base) } ?: throw e
            chaine.proceed(rediriger(requete, trouvee))
        }
    }

    private fun estApi(url: HttpUrl): Boolean {
        val a = actuelle.get()
        return (url.host == origine.host && url.port == origine.port) || (url.host == a.host && url.port == a.port)
    }

    private fun rediriger(requete: Request, base: HttpUrl): Request {
        if (requete.url.host == base.host && requete.url.port == base.port) return requete
        val url = requete.url.newBuilder().scheme(base.scheme).host(base.host).port(base.port).build()
        return requete.newBuilder().url(url).build()
    }

    /** Seulement les échecs où la requête n'a pas pu partir : la rejouer ne crée jamais de doublon. */
    private fun injoignable(e: IOException) = e is ConnectException || e is NoRouteToHostException ||
        (e is SocketTimeoutException && e.message?.contains("connect", ignoreCase = true) == true)

    /** Recherche sur le réseau local (au plus une fois toutes les 20 s). */
    private fun rechercher(echouee: HttpUrl): HttpUrl? {
        val maintenant = System.currentTimeMillis()
        // Une autre requête vient déjà de trouver une nouvelle adresse : on l'utilise
        actuelle.get().takeIf { it != echouee }?.let { return it }
        if (maintenant - derniereRecherche < 20_000) return null
        derniereRecherche = maintenant

        val trouvee = runBlocking { balayer() } ?: return null
        actuelle.set(trouvee)
        contexte?.getSharedPreferences(PREFS, Context.MODE_PRIVATE)?.edit()?.putString(CLE_API, trouvee.toString())?.apply()
        android.util.Log.i("VIVI", "Plateforme retrouvée sur le réseau : $trouvee")
        return trouvee
    }

    private val sonde: OkHttpClient by lazy {
        OkHttpClient.Builder()
            .connectTimeout(700, TimeUnit.MILLISECONDS)
            .readTimeout(4, TimeUnit.SECONDS)
            .callTimeout(5, TimeUnit.SECONDS)
            .build()
    }

    private suspend fun balayer(): HttpUrl? = withContext(Dispatchers.IO) {
        val ip = adresseWifi() ?: return@withContext null
        val prefixe = ip.substringBeforeLast('.')
        // Les adresses les plus probables d'abord (celle de l'APK si elle est sur ce réseau)
        val candidats = (listOf(origine.host).filter { it.startsWith("$prefixe.") } +
            (1..254).map { "$prefixe.$it" }).distinct().filter { it != ip }
        val limite = Semaphore(48)
        val resultats = candidats.map { hote ->
            async { limite.withPermit { origine.newBuilder().host(hote).build().takeIf { repond(it) } } }
        }
        resultats.awaitAll().firstOrNull { it != null }
    }

    private fun repond(api: HttpUrl): Boolean = try {
        sonde.newCall(Request.Builder().url(api.newBuilder().addPathSegment("configuration").build()).header("Accept", "application/json").build())
            .execute().use { r -> r.isSuccessful && (r.body?.string()?.contains("\"devise\"") == true) }
    } catch (_: Exception) {
        false
    }

    /** IPv4 du téléphone sur le Wi-Fi (ou l'Ethernet), ex. « 192.168.1.42 ». */
    private fun adresseWifi(): String? {
        val cm = contexte?.getSystemService(ConnectivityManager::class.java) ?: return null
        val reseau = cm.activeNetwork ?: return null
        val capacites = cm.getNetworkCapabilities(reseau) ?: return null
        if (!capacites.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) && !capacites.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET)) return null
        return cm.getLinkProperties(reseau)?.linkAddresses
            ?.map { it.address }?.firstOrNull { it is Inet4Address && !it.isLoopbackAddress }?.hostAddress
    }
}
