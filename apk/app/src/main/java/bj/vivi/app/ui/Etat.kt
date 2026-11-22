package bj.vivi.app.ui

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.browser.customtabs.CustomTabColorSchemeParams
import androidx.browser.customtabs.CustomTabsIntent
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.draw.clip
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.dp
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.toArgb
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import bj.vivi.app.data.ErreurVivi
import bj.vivi.app.ui.composants.SqueletteListe
import bj.vivi.app.ui.composants.EtatErreur
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.Job
import kotlinx.coroutines.channels.BufferOverflow
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.launch

/** État d'un écran qui charge des données du serveur. */
sealed interface Etat<out T> {
    data object Chargement : Etat<Nothing>
    data class Erreur(val erreur: ErreurVivi) : Etat<Nothing>
    data class Pret<T>(val donnees: T) : Etat<T>
}

val <T> Etat<T>.donnees: T? get() = (this as? Etat.Pret<T>)?.donnees

/**
 * ViewModel de base : un chargement principal (avec actualisation par glissement) et
 * des actions ponctuelles (« enCours » désactive les boutons pendant l'appel).
 */
abstract class EcranViewModel<T> : ViewModel() {
    var etat by mutableStateOf<Etat<T>>(Etat.Chargement)
        protected set
    var actualisation by mutableStateOf(false)
        private set
    var enCours by mutableStateOf(false)
        protected set

    private var tache: Job? = null

    protected abstract suspend fun charger(): Result<T>

    fun recharger(silencieux: Boolean = false) {
        tache?.cancel()
        tache = viewModelScope.launch {
            if (silencieux && etat is Etat.Pret) actualisation = true else etat = Etat.Chargement
            charger()
                .onSuccess { etat = Etat.Pret(it) }
                .onFailure { e ->
                    // Une actualisation ratée garde l'écran affiché et prévient seulement
                    if (actualisation) Messages.erreur(e.message ?: "") else etat = Etat.Erreur(e as ErreurVivi)
                }
            actualisation = false
        }
    }

    /** Lance une action ; en cas d'erreur, son message est affiché et [surErreur] reçoit l'erreur. */
    protected fun action(surErreur: (ErreurVivi) -> Unit = { Messages.erreur(it.message) },bloc: suspend () -> Result<Unit>) {
        if (enCours) return
        viewModelScope.launch {
            enCours = true
            bloc().onFailure { surErreur(it as ErreurVivi) }
            enCours = false
        }
    }

    protected fun remplacer(donnees: T) {
        etat = Etat.Pret(donnees)
    }
}

/** Affiche chargement, erreur avec « Réessayer », ou le contenu (avec tirer pour actualiser). */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun <T> AvecEtat(
    vm: EcranViewModel<T>,
    modifier: Modifier = Modifier,
    actualisable: Boolean = true,
    squelette: @Composable (Modifier) -> Unit = { SqueletteListe(it) },
    contenu: @Composable (T) -> Unit,
) {
    // Fondu entre squelette de chargement, erreur et contenu (pas de saut brutal)
    androidx.compose.animation.AnimatedContent(
        targetState = vm.etat,
        contentKey = { it::class },
        transitionSpec = {
            androidx.compose.animation.fadeIn(androidx.compose.animation.core.tween(260)) togetherWith
                androidx.compose.animation.fadeOut(androidx.compose.animation.core.tween(160))
        },
        label = "etat",
    ) { etat ->
    when (val e = etat) {
        Etat.Chargement -> squelette(modifier)
        is Etat.Erreur -> Box(modifier.fillMaxSize()) { EtatErreur(e.erreur.message, e.erreur.horsLigne, onReessayer = { vm.recharger() }) }
        is Etat.Pret -> Box(modifier.fillMaxSize()) {
            if (actualisable) {
                PullToRefreshBox(isRefreshing = vm.actualisation, onRefresh = { vm.recharger(silencieux = true) }, modifier = Modifier.fillMaxSize()) {
                    contenu(e.donnees)
                }
            } else {
                contenu(e.donnees)
            }
            // Action en cours (enregistrer, encaisser, bloquer…) : l'écran est voilé et ne reçoit plus d'appui
            VoileAction(vm.enCours)
        }
    }
    }
}

/**
 * Voile d'action : apparaît après 250 ms (une action instantanée ne fait pas clignoter l'écran),
 * bloque les appuis et affiche « Un instant… ». Annoncé par TalkBack.
 */
@Composable
fun VoileAction(actif: Boolean, texte: String = "Un instant…") {
    var visible by remember { mutableStateOf(false) }
    LaunchedEffect(actif) {
        if (actif) { kotlinx.coroutines.delay(250); visible = true } else visible = false
    }
    androidx.compose.animation.AnimatedVisibility(
        visible && actif, enter = androidx.compose.animation.fadeIn(), exit = androidx.compose.animation.fadeOut(),
    ) {
        Box(
            Modifier.fillMaxSize()
                .background(Vivi.Ink.copy(alpha = .28f))
                .pointerInput(Unit) { awaitPointerEventScope { while (true) awaitPointerEvent() } }
                .semantics { liveRegion = LiveRegionMode.Polite; contentDescription = texte },
            contentAlignment = Alignment.Center,
        ) {
            Row(
                Modifier.clip(RoundedCornerShape(16.dp)).background(Vivi.White).padding(horizontal = 22.dp, vertical = 16.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                CircularProgressIndicator(Modifier.size(24.dp), color = Vivi.Teal700, strokeWidth = 3.dp)
                Spacer(Modifier.width(14.dp))
                Text(texte, style = MaterialTheme.typography.titleSmall, color = Vivi.Ink)
            }
        }
    }
}

/** Messages courts (Snackbar) émis depuis n'importe quel ViewModel ; affichés par l'écran racine. */
enum class TypeMessage { Succes, Erreur, Info }

data class Message(val texte: String, val type: TypeMessage)

object Messages {
    private val _flux = MutableSharedFlow<Message>(extraBufferCapacity = 4, onBufferOverflow = BufferOverflow.DROP_OLDEST)
    val flux: SharedFlow<Message> = _flux

    /** Confirmation d'une action réussie (bandeau vert). */
    fun afficher(texte: String, type: TypeMessage = TypeMessage.Succes) {
        if (texte.isNotBlank()) _flux.tryEmit(Message(texte, type))
    }

    /** Échec ou action impossible (bandeau rouge, affiché plus longtemps). */
    fun erreur(texte: String) = afficher(texte, TypeMessage.Erreur)

    /** Indication neutre : à vérifier, à activer… (bandeau turquoise foncé). */
    fun info(texte: String) = afficher(texte, TypeMessage.Info)
}

/* ---------- Liens externes ---------- */

/** Ouvre une page (paiement FedaPay, conditions) dans un Custom Tab aux couleurs de VIVI. */
fun ouvrirPage(context: Context, url: String) {
    val couleurs = CustomTabColorSchemeParams.Builder().setToolbarColor(Vivi.White.toArgb()).setNavigationBarColor(Vivi.White.toArgb()).build()
    try {
        CustomTabsIntent.Builder().setDefaultColorSchemeParams(couleurs).setShowTitle(true).setUrlBarHidingEnabled(false).build()
            .launchUrl(context, Uri.parse(url))
    } catch (e: ActivityNotFoundException) {
        ouvrirLien(context, url)
    }
}

/** Ouvre un lien dans l'application qui sait le gérer (Maps, WhatsApp, téléphone…). */
fun ouvrirLien(context: Context, url: String) {
    try {
        context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
    } catch (e: ActivityNotFoundException) {
        Messages.erreur("Aucune application ne peut ouvrir ce lien.")
    }
}

fun appeler(context: Context, telephone: String) = ouvrirLien(context, "tel:" + telephone.filter { it.isDigit() || it == '+' })

fun partager(context: Context, texte: String) {
    val envoi = Intent(Intent.ACTION_SEND).setType("text/plain").putExtra(Intent.EXTRA_TEXT, texte)
    context.startActivity(Intent.createChooser(envoi, "Partager").addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
}
