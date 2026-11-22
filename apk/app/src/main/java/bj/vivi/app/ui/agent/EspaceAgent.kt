@file:OptIn(ExperimentalMaterial3Api::class)

package bj.vivi.app.ui.agent

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Campaign
import androidx.compose.material.icons.outlined.ContentCopy
import androidx.compose.material.icons.outlined.Fingerprint
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.Share
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.EspaceAgent
import bj.vivi.app.data.appel
import bj.vivi.app.data.corps
import bj.vivi.app.ui.AvecEtat
import bj.vivi.app.ui.EcranViewModel
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.Messages
import bj.vivi.app.ui.composants.Bandeau
import bj.vivi.app.ui.composants.BoutonContour
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.BoutonTexte
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.ChampTexte
import bj.vivi.app.ui.composants.EtatVide
import bj.vivi.app.ui.composants.Marque
import bj.vivi.app.ui.composants.PhotoLogement
import bj.vivi.app.ui.composants.Puce
import bj.vivi.app.ui.composants.PuceStatut
import bj.vivi.app.ui.composants.Separateur
import bj.vivi.app.ui.composants.SqueletteTableauDeBord
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.donnees
import bj.vivi.app.ui.partager
import bj.vivi.app.ui.pluriel
import bj.vivi.app.ui.theme.Outfit
import bj.vivi.app.ui.theme.Vivi

/**
 * Espace de l'agent marketing (GET /agent) : liens d'affiliation à partager, commissions gagnées,
 * solde et retraits. La commission est créditée au démarrage du séjour (QR Code scanné).
 */
class EspaceAgentViewModel : EcranViewModel<EspaceAgent>() {
    var compte by mutableStateOf<bj.vivi.app.data.CompteRetrait?>(null)
        private set
    init { recharger() }
    override suspend fun charger() = appel { espaceAgent() }.map { it.data }.onSuccess {
        appel { compteRetrait() }.onSuccess { compte = it.data }
    }

    fun retirer(montant: Int?, fait: () -> Unit) = action {
        appel { retraitAgent(corps("montant" to montant)) }.map {
            Messages.afficher(it.message ?: "Demande de retrait envoyée.")
            fait()
            recharger(silencieux = true)
        }
    }
}

@Composable
fun EcranEspaceAgent(onRetour: (() -> Unit)?, onLogement: (String) -> Unit, onCompteRetrait: () -> Unit = {}, vm: EspaceAgentViewModel = viewModel()) {
    var retrait by remember { mutableStateOf(false) }
    val contexte = LocalContext.current
    androidx.lifecycle.compose.LifecycleEventEffect(androidx.lifecycle.Lifecycle.Event.ON_RESUME) { if (vm.etat.donnees != null) vm.recharger(silencieux = true) }
    AvecEtat(vm, Modifier.fillMaxSize().background(Vivi.Sand), squelette = { SqueletteTableauDeBord(it) }) { a ->
        Column(Modifier.fillMaxSize().background(Vivi.Sand).verticalScroll(rememberScrollState())) {
            // ---------- En-tête : solde et chiffres ----------
            Column(Modifier.fillMaxWidth().background(Vivi.Teal900).statusBarsPadding().padding(horizontal = 16.dp).padding(top = 8.dp, bottom = 20.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    if (onRetour != null) IconButton(onClick = onRetour) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Retour", tint = Vivi.White) }
                    Marque(blanc = true, taille = 28.dp)
                    Spacer(Modifier.weight(1f))
                    Puce("Agent marketing", TonPuce.Sombre, Icons.Outlined.Campaign)
                }
                Spacer(Modifier.height(18.dp))
                Text("Solde disponible", color = Vivi.Teal200, fontSize = 14.sp)
                Text(Fmt.fcfa(a.solde), fontFamily = Outfit, fontWeight = FontWeight.Bold, fontSize = 32.sp, color = Vivi.White)
                Spacer(Modifier.height(14.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Chiffre("Ce mois-ci", Fmt.fcfa(a.ceMois), Modifier.weight(1f))
                    Chiffre("Total gagné", Fmt.fcfa(a.totalGagne), Modifier.weight(1f))
                }
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Chiffre("Séjours réalisés", a.sejours.toString(), Modifier.weight(1f))
                    Chiffre("À venir", a.aVenir.toString(), Modifier.weight(1f))
                }
                Spacer(Modifier.height(16.dp))
                bj.vivi.app.ui.compte.LigneCompteRetrait(vm.compte, onCompteRetrait, clair = true)
                Spacer(Modifier.height(10.dp))
                BoutonPrincipal("Demander un retrait", { if (vm.compte?.configure == true) retrait = true else onCompteRetrait() }, Modifier.fillMaxWidth(), actif = a.solde >= a.retraitMinimum)
                if (a.solde < a.retraitMinimum) {
                    Text("Retrait possible à partir de ${Fmt.fcfa(a.retraitMinimum)}.", color = Vivi.Teal100, fontSize = 13.sp, modifier = Modifier.padding(top = 6.dp))
                }
            }

            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                Carte(fond = Vivi.Teal50) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text("Mon code agent", style = MaterialTheme.typography.bodySmall)
                            Text(a.codeAgent, fontFamily = Outfit, fontWeight = FontWeight.Bold, fontSize = 22.sp, color = Vivi.Teal800, letterSpacing = 2.sp)
                        }
                        val context = LocalContext.current
                        IconButton(onClick = { copier(context, a.codeAgent, "Code copié.") }) { Icon(Icons.Outlined.ContentCopy, contentDescription = "Copier mon code", tint = Vivi.Teal700) }
                    }
                }
                Bandeau(
                    "Partagez le lien d’un logement. Quand un client réserve par ce lien, votre part vous est créditée au début de son séjour.",
                    ton = TonPuce.Teal, icone = Icons.Outlined.Info,
                )

                // ---------- Logements promus ----------
                TitreSection("Mes logements à promouvoir", surtitre = pluriel(a.logements.size, "logement"))
                if (a.logements.isEmpty()) {
                    EtatVide(Icons.Outlined.Campaign, "Aucun logement pour l’instant", "Un propriétaire doit vous désigner comme agent pour vous confier ses logements.")
                }
                a.logements.forEach { l -> CarteLogementPromu(l, onLogement) }

                // ---------- Retraits ----------
                if (a.retraits.isNotEmpty()) {
                    Carte {
                        Column {
                            TitreSection("Mes demandes de retrait")
                            a.retraits.forEach { d ->
                                Separateur(Modifier.padding(vertical = 4.dp))
                                Row(Modifier.fillMaxWidth().padding(vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Column(Modifier.weight(1f)) {
                                        Text(Fmt.fcfa(d.montant), style = MaterialTheme.typography.titleSmall)
                                        Text("${d.reference} · ${Fmt.numerique(d.le)}" + (d.modeReglement?.let { " · $it" } ?: ""), style = MaterialTheme.typography.bodySmall)
                                        d.compte?.numero?.let { Text("Sur ${d.compte.moyen.orEmpty()} · ${bj.vivi.app.data.numeroLisible(it)}", style = MaterialTheme.typography.bodySmall) }
                                    }
                                    PuceStatut(d.statut)
                                }
                            }
                        }
                    }
                }

                // ---------- Mouvements ----------
                Carte {
                    Column {
                        TitreSection("Mes commissions")
                        if (a.mouvements.isEmpty()) Text("Aucune commission pour l’instant.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted, modifier = Modifier.padding(vertical = 8.dp))
                        a.mouvements.forEach { m ->
                            Separateur(Modifier.padding(vertical = 4.dp))
                            Row(Modifier.fillMaxWidth().padding(vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                                Column(Modifier.weight(1f)) {
                                    Text(m.libelle ?: m.type?.libelle.orEmpty(), style = MaterialTheme.typography.bodyMedium, color = Vivi.Ink)
                                    Text(Fmt.numerique(m.le) + (m.reservation?.let { " · $it" } ?: ""), style = MaterialTheme.typography.bodySmall)
                                }
                                Text((if (m.montant > 0) "+ " else "− ") + Fmt.fcfa(kotlin.math.abs(m.montant)), fontWeight = FontWeight.SemiBold,
                                    color = if (m.montant > 0) Vivi.Success else Vivi.Ink)
                            }
                        }
                    }
                }
            }
        }
    }

    val a = vm.etat.donnees
    if (retrait && a != null) {
        var montant by remember { mutableStateOf(a.solde.toString()) }
        ModalBottomSheet(onDismissRequest = { if (!vm.enCours) retrait = false }, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Vivi.White) {
            Column(Modifier.padding(horizontal = 20.dp).padding(bottom = 24.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Text("Demander un retrait", style = MaterialTheme.typography.headlineSmall)
                ChampTexte(montant, { montant = it.filter(Char::isDigit).take(9) }, "Montant (FCFA)", clavier = KeyboardType.Number,
                    aide = "Entre ${Fmt.fcfa(a.retraitMinimum)} et ${Fmt.fcfa(a.solde)}. Payé par une caissière VIVI sur ${vm.compte?.resume.orEmpty()}.")
                val m = montant.toIntOrNull() ?: 0
                BoutonPrincipal(if (vm.enCours) "Envoi de la demande…" else "Envoyer la demande", { vm.retirer(m) { retrait = false } }, Modifier.fillMaxWidth(), actif = m in a.retraitMinimum..a.solde, enCours = vm.enCours)
            }
        }
    }
}

@Composable
private fun Chiffre(libelle: String, valeur: String, modifier: Modifier = Modifier) {
    Column(modifier.clip(RoundedCornerShape(12.dp)).background(Vivi.White.copy(alpha = .08f)).padding(12.dp)) {
        Text(libelle, color = Vivi.Teal200, fontSize = 12.sp)
        Text(valeur, color = Vivi.White, fontWeight = FontWeight.Bold, fontSize = 17.sp, maxLines = 1)
    }
}

@Composable
private fun CarteLogementPromu(l: EspaceAgent.LogementPromu, onLogement: (String) -> Unit) {
    val context = LocalContext.current
    Carte(remplissage = PaddingValues(12.dp)) {
        Column {
            Row(verticalAlignment = Alignment.CenterVertically) {
                PhotoLogement(l.photo, null, Modifier.size(72.dp).clip(RoundedCornerShape(12.dp)))
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Text(l.titre, style = MaterialTheme.typography.titleMedium, maxLines = 2)
                    Text(listOfNotNull(l.quartier, l.ville).joinToString(", "), style = MaterialTheme.typography.bodySmall)
                    l.proprietaire?.let { Text("Propriétaire : $it", style = MaterialTheme.typography.bodySmall) }
                }
            }
            Spacer(Modifier.height(10.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                Puce(l.part?.libelle?.let { "Ma part : $it" } ?: "Part non fixée", if (l.part != null) TonPuce.Succes else TonPuce.Neutre)
                if (!l.enLigne) Puce("Hors ligne", TonPuce.Orange)
            }
            Spacer(Modifier.height(10.dp))
            Row {
                StatPetite("Séjours", l.sejours.toString(), Modifier.weight(1f))
                StatPetite("À venir", l.aVenir.toString(), Modifier.weight(1f))
                StatPetite("Gagné", Fmt.fcfa(l.gains), Modifier.weight(1.4f))
            }
            Separateur(Modifier.padding(vertical = 8.dp))
            Box(Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).background(Vivi.Sand).padding(10.dp)) {
                Text(l.lien, style = MaterialTheme.typography.bodySmall, maxLines = 2)
            }
            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                BoutonPrincipal("Partager", {
                    partager(context, "Réservez « ${l.titre} » (${listOfNotNull(l.quartier, l.ville).joinToString(", ")}) sur VIVI, à l’heure, à la demi-journée ou à la journée : ${l.lien}")
                }, Modifier.weight(1f), icone = Icons.Outlined.Share, actif = l.enLigne)
                BoutonContour("Copier", { copier(context, l.lien, "Lien copié.") }, icone = Icons.Outlined.ContentCopy)
            }
            if (l.enLigne) BoutonTexte("Voir la fiche", { onLogement(l.slug) }, icone = Icons.Outlined.Visibility)
            else Text("Ce logement n’est pas visible des clients pour l’instant : son lien ne fonctionne pas.", style = MaterialTheme.typography.bodySmall)
        }
    }
}

@Composable
private fun StatPetite(libelle: String, valeur: String, modifier: Modifier = Modifier) {
    Column(modifier) {
        Text(libelle, style = MaterialTheme.typography.bodySmall)
        Text(valeur, style = MaterialTheme.typography.titleSmall, color = Vivi.Ink, maxLines = 1)
    }
}

private fun copier(context: Context, texte: String, message: String) {
    context.getSystemService(ClipboardManager::class.java)?.setPrimaryClip(ClipData.newPlainText("VIVI", texte))
    Messages.afficher(message)
}
