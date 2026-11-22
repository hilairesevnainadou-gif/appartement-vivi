package bj.vivi.app.ui

import android.content.Context
import android.content.ContextWrapper
import android.os.Build
import android.os.SystemClock
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricManager.Authenticators.BIOMETRIC_STRONG
import androidx.biometric.BiometricManager.Authenticators.BIOMETRIC_WEAK
import androidx.biometric.BiometricManager.Authenticators.DEVICE_CREDENTIAL
import androidx.biometric.BiometricPrompt
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Fingerprint
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import bj.vivi.app.data.Reseau
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.BoutonTexte
import bj.vivi.app.ui.composants.Marque
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/**
 * Sécurité de l'application :
 * - verrouillage par empreinte (ou code, schéma du téléphone) à l'ouverture et après 1 min en arrière-plan ;
 * - confirmation d'identité avant les opérations sensibles (retrait, changement du compte de retrait).
 * Réglage gardé sur le téléphone ; actif seulement quand un compte est connecté.
 */
object Verrou {
    private const val DELAI_MS = 60_000L
    private var prefs: android.content.SharedPreferences? = null
    private var miseEnArrierePlan = 0L
    private val _verrouille = MutableStateFlow(false)
    val verrouille: StateFlow<Boolean> = _verrouille

    fun initialiser(context: Context) {
        prefs = context.getSharedPreferences("vivi_securite", Context.MODE_PRIVATE)
        _verrouille.value = actif && Reseau.session.jeton != null
    }

    var actif: Boolean
        get() = prefs?.getBoolean("verrou_empreinte", false) == true
        set(v) { prefs?.edit()?.putBoolean("verrou_empreinte", v)?.apply() }

    fun surArrierePlan() { miseEnArrierePlan = SystemClock.elapsedRealtime() }

    fun surPremierPlan() {
        if (actif && Reseau.session.jeton != null && miseEnArrierePlan > 0 && SystemClock.elapsedRealtime() - miseEnArrierePlan > DELAI_MS) {
            _verrouille.value = true
        }
    }

    fun deverrouiller() { _verrouille.value = false }

    /** Empreinte, visage ou code du téléphone disponibles sur cet appareil. */
    fun disponible(context: Context) = BiometricManager.from(context).canAuthenticate(authentificateurs()) == BiometricManager.BIOMETRIC_SUCCESS

    /** Android 11+ : biométrie forte ou code ; avant : biométrie ou code (combinaison permise par l'API). */
    fun authentificateurs() = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) BIOMETRIC_STRONG or DEVICE_CREDENTIAL else BIOMETRIC_WEAK or DEVICE_CREDENTIAL
}

fun Context.activite(): FragmentActivity? {
    var c: Context = this
    while (c is ContextWrapper) {
        if (c is FragmentActivity) return c
        c = c.baseContext
    }
    return null
}

/**
 * Demande l'empreinte (ou le code du téléphone) puis appelle [ok].
 * Sans moyen de verrouillage configuré sur le téléphone, [ok] est appelé directement.
 */
fun confirmerIdentite(context: Context, titre: String, sousTitre: String? = null, ok: () -> Unit) {
    val activite = context.activite()
    if (activite == null || !Verrou.disponible(context)) { ok(); return }
    val invite = BiometricPrompt(activite, ContextCompat.getMainExecutor(activite), object : BiometricPrompt.AuthenticationCallback() {
        override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) = ok()
        override fun onAuthenticationError(code: Int, message: CharSequence) {
            if (code != BiometricPrompt.ERROR_USER_CANCELED && code != BiometricPrompt.ERROR_NEGATIVE_BUTTON && code != BiometricPrompt.ERROR_CANCELED) {
                Messages.erreur(message.toString())
            }
        }
    })
    invite.authenticate(
        BiometricPrompt.PromptInfo.Builder()
            .setTitle(titre)
            .apply { sousTitre?.let { setSubtitle(it) } }
            .setAllowedAuthenticators(Verrou.authentificateurs())
            .build(),
    )
}

/** Écran de verrouillage : affiché par-dessus l'application tant que l'identité n'est pas confirmée. */
@Composable
fun EcranVerrou() {
    val context = LocalContext.current
    val deverrouiller = { confirmerIdentite(context, "Déverrouiller VIVI", "Empreinte ou code du téléphone") { Verrou.deverrouiller() } }
    LaunchedEffect(Unit) { deverrouiller() }
    Column(
        Modifier.fillMaxSize().background(Vivi.Teal900).padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center,
    ) {
        Marque(blanc = true, taille = 44.dp)
        Spacer(Modifier.height(40.dp))
        Icon(Icons.Outlined.Fingerprint, contentDescription = null, tint = Vivi.Orange400, modifier = Modifier.padding(8.dp).height(72.dp).fillMaxWidth())
        Spacer(Modifier.height(16.dp))
        Text("VIVI est verrouillé", color = Vivi.White, fontSize = 22.sp, textAlign = TextAlign.Center)
        Spacer(Modifier.height(8.dp))
        Text("Touchez le capteur d’empreinte ou saisissez le code de votre téléphone.", color = Vivi.Teal200, textAlign = TextAlign.Center)
        Spacer(Modifier.height(32.dp))
        BoutonPrincipal("Déverrouiller", deverrouiller, Modifier.fillMaxWidth(), icone = Icons.Outlined.Fingerprint)
        Spacer(Modifier.height(8.dp))
        BoutonTexte("Se déconnecter", { Verrou.deverrouiller(); Reseau.session.fermer() }, couleur = Vivi.Teal200)
    }
}
