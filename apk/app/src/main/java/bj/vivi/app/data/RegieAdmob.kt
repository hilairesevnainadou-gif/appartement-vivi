package bj.vivi.app.data

import android.app.Activity
import android.content.Context
import android.util.Log
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import com.google.android.gms.ads.AdError
import com.google.android.gms.ads.AdRequest
import com.google.android.gms.ads.FullScreenContentCallback
import com.google.android.gms.ads.LoadAdError
import com.google.android.gms.ads.MobileAds
import com.google.android.gms.ads.appopen.AppOpenAd
import com.google.android.ump.ConsentInformation
import com.google.android.ump.ConsentRequestParameters
import com.google.android.ump.UserMessagingPlatform
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume

/**
 * Google AdMob : consentement (UMP) d'abord, puis démarrage du SDK, seulement si l'API donne au moins un bloc AdMob.
 * Les blocs d'annonces viennent de GET /publicites ; l'identifiant de l'application est dans le manifeste (viviAdmobAppId).
 */
object RegieAdmob {
    /** Consentement recueilli (ou non requis) et SDK démarré : les annonces peuvent être chargées. */
    var prete by mutableStateOf(false)
        private set

    /** « Confidentialité des annonces » à proposer dans Compte (Espace économique européen, Royaume-Uni, Suisse). */
    var choixAProposer by mutableStateOf(false)
        private set

    private var lancee = false
    private var demarree = false

    fun preparer(activite: Activity, test: Boolean) {
        if (lancee) return
        lancee = true
        val infos = UserMessagingPlatform.getConsentInformation(activite)
        infos.requestConsentInfoUpdate(
            activite, ConsentRequestParameters.Builder().build(),
            {
                UserMessagingPlatform.loadAndShowConsentFormIfRequired(activite) { erreur ->
                    erreur?.let { Log.w("VIVI", "Consentement publicité : ${it.message}") }
                    relireChoix(infos)
                    if (infos.canRequestAds()) demarrer(activite)
                }
            },
            { erreur ->
                Log.w("VIVI", "Consentement publicité indisponible : ${erreur.message}")
                relireChoix(infos)
                // Blocs de démonstration de Google : utilisables même si aucun message UMP n'est encore créé dans AdMob
                if (infos.canRequestAds() || test) demarrer(activite)
            },
        )
        // Consentement déjà donné lors d'une ouverture précédente : pas d'attente
        if (infos.canRequestAds()) demarrer(activite)
    }

    private fun relireChoix(infos: ConsentInformation) {
        choixAProposer = infos.privacyOptionsRequirementStatus == ConsentInformation.PrivacyOptionsRequirementStatus.REQUIRED
    }

    /** Compte › Confidentialité des annonces. */
    fun ouvrirChoix(activite: Activity, surErreur: (String) -> Unit) =
        UserMessagingPlatform.showPrivacyOptionsForm(activite) { erreur -> erreur?.let { surErreur(it.message) } }

    private fun demarrer(context: Context) {
        if (demarree) return
        demarree = true
        val app = context.applicationContext
        Thread({
            MobileAds.initialize(app) { android.os.Handler(android.os.Looper.getMainLooper()).post { prete = true } }
        }, "vivi-admob").start()
    }

    /** Annonce d'ouverture (App Open) ; null si elle n'arrive pas. */
    suspend fun chargerOuverture(context: Context, bloc: String): AppOpenAd? = suspendCancellableCoroutine { suite ->
        AppOpenAd.load(context, bloc, AdRequest.Builder().build(), object : AppOpenAd.AppOpenAdLoadCallback() {
            override fun onAdLoaded(annonce: AppOpenAd) { if (suite.isActive) suite.resume(annonce) }
            override fun onAdFailedToLoad(erreur: LoadAdError) {
                Log.w("VIVI", "AdMob ouverture : ${erreur.message}")
                if (suite.isActive) suite.resume(null)
            }
        })
    }

    fun montrerOuverture(activite: Activity, annonce: AppOpenAd, surAffichee: () -> Unit) {
        annonce.fullScreenContentCallback = object : FullScreenContentCallback() {
            override fun onAdShowedFullScreenContent() = surAffichee()
            override fun onAdFailedToShowFullScreenContent(erreur: AdError) { Log.w("VIVI", "AdMob ouverture : ${erreur.message}") }
        }
        annonce.show(activite)
    }
}
