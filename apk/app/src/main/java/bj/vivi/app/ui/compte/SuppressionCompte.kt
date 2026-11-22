@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)

package bj.vivi.app.ui.compte

import android.content.Context
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Chat
import androidx.compose.material.icons.outlined.DeleteOutline
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.credentials.ClearCredentialStateRequest
import androidx.credentials.CredentialManager
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.Catalogue
import bj.vivi.app.data.ErreurVivi
import bj.vivi.app.data.Push
import bj.vivi.app.data.Reseau
import bj.vivi.app.data.SuppressionCompte
import bj.vivi.app.data.appel
import bj.vivi.app.data.corps
import bj.vivi.app.ui.AvecEtat
import bj.vivi.app.ui.CodesAgent
import bj.vivi.app.ui.EcranViewModel
import bj.vivi.app.ui.Messages
import bj.vivi.app.ui.Verrou
import bj.vivi.app.ui.composants.Bandeau
import bj.vivi.app.ui.composants.BarreTitre
import bj.vivi.app.ui.composants.BoutonContour
import bj.vivi.app.ui.composants.BoutonSecondaire
import bj.vivi.app.ui.composants.BoutonTexte
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.ChampTexte
import bj.vivi.app.ui.composants.SqueletteDetail
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.ouvrirLien
import bj.vivi.app.ui.ouvrirPage
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.NonCancellable
import kotlinx.coroutines.withContext

/**
 * « Supprimer mon compte » (exigé par Google Play), comme la page web resources/views/espace/suppression-compte.blade.php :
 * ce qui empêche la suppression, ou ce qui est perdu, puis code de confirmation et case à cocher.
 */
class SuppressionCompteViewModel : EcranViewModel<SuppressionCompte>() {
    var codeEnvoye by mutableStateOf(false)
        private set
    var code by mutableStateOf("")
        private set
    var codeDev by mutableStateOf<String?>(null)   // en local seulement : code renvoyé par le serveur pour tester
        private set
    var accord by mutableStateOf(false)
    var erreurCode by mutableStateOf<String?>(null)
        private set

    init { recharger() }

    override suspend fun charger() = appel { suppressionCompte() }.map { it.data }

    fun saisir(texte: String) {
        code = texte.filter(Char::isDigit).take(6)
        erreurCode = null
    }

    fun demanderCode() = action(surErreur = ::refus) {
        appel { codeSuppression() }.map {
            codeEnvoye = true
            codeDev = it.codeDev
            code = it.codeDev.orEmpty()
            erreurCode = null
            Messages.afficher(it.message ?: "Code envoyé.")
        }
    }

    fun supprimer(context: Context) {
        val appli = context.applicationContext
        action(surErreur = { e -> if (e.champ("code") != null) erreurCode = e.champ("code") else refus(e) }) {
            // Dès la suppression, la vérification des notifications peut recevoir un 401 et fermer cet écran :
            // la fin (nettoyage du téléphone, message) doit quand même s'exécuter.
            withContext(NonCancellable) {
                appel { supprimerCompte(corps("code" to code)) }.map {
                    oublierCompte(appli)
                    Messages.afficher(it.message ?: "Votre compte est supprimé.")
                }
            }
        }
    }

    /** Obstacle apparu entre-temps, ou code demandé trop tôt : message affiché, obstacles relus. */
    private fun refus(e: ErreurVivi) {
        Messages.erreur(e.message)
        if (e.champ("compte") != null) recharger(silencieux = true)
    }

    /** Plus rien de ce compte sur le téléphone ; la session fermée ramène à l'accueil visiteur. */
    private suspend fun oublierCompte(appli: Context) {
        Push.oublier(appli)
        Verrou.actif = false
        CodesAgent.oublier()
        Reseau.session.fermer()
        try {
            CredentialManager.create(appli).clearCredentialState(ClearCredentialStateRequest())
        } catch (e: CancellationException) {
            throw e
        } catch (_: Exception) {
            // Téléphone sans services Google : rien à oublier
        }
    }
}

@Composable
fun EcranSuppressionCompte(onRetour: () -> Unit, vm: SuppressionCompteViewModel = viewModel()) {
    Scaffold(containerColor = Vivi.Sand, topBar = { BarreTitre("Supprimer mon compte", onRetour) }) { marges ->
        AvecEtat(vm, Modifier.padding(marges), squelette = { SqueletteDetail(it) }) { s ->
            Column(
                Modifier.fillMaxSize().imePadding().verticalScroll(rememberScrollState()).padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                if (s.possible) SuppressionPossible(s, vm, onRetour) else SuppressionImpossible(s, onRetour)
            }
        }
    }
}

@Composable
private fun SuppressionImpossible(s: SuppressionCompte, onRetour: () -> Unit) {
    val context = LocalContext.current
    Carte {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            TitreSection("Pas encore possible")
            s.obstacles.forEach { LignePuce(it.message, Vivi.OrangeInk) }
            Catalogue.configuration?.contact?.whatsapp?.let { lien ->
                BoutonTexte("Besoin d’aide ? Écrivez-nous", { ouvrirLien(context, lien) }, icone = Icons.AutoMirrored.Outlined.Chat)
            }
        }
    }
    BoutonSecondaire("Retour à mon compte", onRetour, Modifier.fillMaxWidth())
}

@Composable
private fun SuppressionPossible(s: SuppressionCompte, vm: SuppressionCompteViewModel, onRetour: () -> Unit) {
    val context = LocalContext.current
    Carte {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            TitreSection("La suppression est définitive")
            s.pertes.forEach { LignePuce(it, Vivi.Danger) }
            Text(
                "Nous gardons les réservations et les paiements sans votre nom, pour la comptabilité. Vos avis restent, sans votre nom.",
                style = MaterialTheme.typography.bodySmall,
            )
            Catalogue.configuration?.pages?.suppressionCompte?.let { lien ->
                BoutonTexte("En savoir plus", { ouvrirPage(context, lien) }, icone = Icons.Outlined.Info)
            }
        }
    }

    val parSms = s.canal == "sms"
    Carte {
        Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
            TitreSection("Confirmer")
            if (!vm.codeEnvoye) {
                Text("Un code sera envoyé ${if (parSms) "par SMS au" else "à"} ${s.destination.orEmpty()}.", style = MaterialTheme.typography.bodyMedium)
                BoutonContour("Recevoir un code", vm::demanderCode, Modifier.fillMaxWidth(), enCours = vm.enCours, danger = true)
            } else {
                ChampTexte(
                    vm.code, vm::saisir, "Code envoyé ${if (parSms) "par SMS au" else "à"} ${s.destination.orEmpty()}",
                    clavier = KeyboardType.NumberPassword, erreur = vm.erreurCode,
                )
                vm.codeDev?.let { Bandeau("Mode test : code $it rempli automatiquement.", ton = TonPuce.Orange, icone = Icons.Outlined.Info) }
                Row(
                    Modifier.fillMaxWidth().heightIn(min = 48.dp).clip(RoundedCornerShape(12.dp))
                        .toggleable(vm.accord, role = Role.Checkbox) { vm.accord = it },
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Checkbox(vm.accord, null, colors = CheckboxDefaults.colors(checkedColor = Vivi.Danger, uncheckedColor = Vivi.LineStrong))
                    Spacer(Modifier.width(10.dp))
                    Text("Je veux supprimer définitivement mon compte.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Ink, modifier = Modifier.weight(1f))
                }
                BoutonContour(
                    "Supprimer mon compte", { vm.supprimer(context) }, Modifier.fillMaxWidth(),
                    actif = vm.accord && vm.code.length == 6, enCours = vm.enCours, icone = Icons.Outlined.DeleteOutline, danger = true,
                )
                BoutonTexte("Renvoyer un code", vm::demanderCode)
            }
        }
    }
    BoutonSecondaire("Garder mon compte", onRetour, Modifier.fillMaxWidth(), actif = !vm.enCours)
}

@Composable
private fun LignePuce(texte: String, couleur: Color) {
    Row {
        Box(Modifier.padding(top = 8.dp).size(6.dp).clip(CircleShape).background(couleur))
        Spacer(Modifier.width(10.dp))
        Text(texte, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f))
    }
}
