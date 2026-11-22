@file:OptIn(ExperimentalMaterial3Api::class)

package bj.vivi.app.ui.pro

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
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Campaign
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.PersonAdd
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExtendedFloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Scaffold
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
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.AgentsPage
import bj.vivi.app.data.appel
import bj.vivi.app.data.corps
import bj.vivi.app.ui.AvecEtat
import bj.vivi.app.ui.EcranViewModel
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.Messages
import bj.vivi.app.ui.composants.Avatar
import bj.vivi.app.ui.composants.Bandeau
import bj.vivi.app.ui.composants.BarreTitre
import bj.vivi.app.ui.composants.BoutonContour
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.BoutonTexte
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.ChampTexte
import bj.vivi.app.ui.composants.EtatVide
import bj.vivi.app.ui.composants.Puce
import bj.vivi.app.ui.composants.Segmente
import bj.vivi.app.ui.composants.Separateur
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.donnees
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.launch

/** Agents marketing du propriétaire (GET · POST /pro/agents, PUT /pro/agents/{id}). */
class AgentsProViewModel : EcranViewModel<AgentsPage>() {
    var periode by mutableStateOf("mois")
        private set
    var erreurs by mutableStateOf<Map<String, String>>(emptyMap())
        private set

    init { recharger() }
    override suspend fun charger() = appel { agentsPro(periode) }.map { it.data }

    fun periode(p: String) {
        periode = p
        recharger(silencieux = true)
    }

    fun oublierErreurs() { erreurs = emptyMap() }

    fun designer(nom: String, identifiant: String, logements: Set<Long>, fait: () -> Unit) {
        erreurs = emptyMap()
        action(surErreur = { e -> erreurs = e.champs.ifEmpty { mapOf("identifiant" to e.message) } }) {
            appel { designerAgent(corps("nom" to nom.trim(), "identifiant" to identifiant.trim(), "logements" to logements.toList())) }
                .map { Messages.afficher(it.message ?: "Agent désigné."); fait(); recharger(silencieux = true) }
        }
    }

    fun modifier(agent: Long, logements: Set<Long>, fait: () -> Unit) = action {
        appel { modifierAgent(agent, corps("logements" to logements.toList())) }
            .map { Messages.afficher(it.message ?: "Modifié."); fait(); recharger(silencieux = true) }
    }
}

@Composable
fun EcranAgentsPro(onRetour: () -> Unit, vm: AgentsProViewModel = viewModel()) {
    var designer by remember { mutableStateOf(false) }
    var edition by remember { mutableStateOf<AgentsPage.Ligne?>(null) }

    Scaffold(
        containerColor = Vivi.Sand,
        topBar = { BarreTitre("Agents marketing", onRetour) },
        floatingActionButton = {
            ExtendedFloatingActionButton(
                onClick = { vm.oublierErreurs(); designer = true }, containerColor = Vivi.Orange400, contentColor = Vivi.Ink,
                icon = { Icon(Icons.Outlined.PersonAdd, null) }, text = { Text("Désigner un agent", fontWeight = FontWeight.SemiBold, color = Vivi.Ink) },
            )
        },
    ) { marges ->
        AvecEtat(vm, Modifier.padding(marges)) { page ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Bandeau(
                    "Un agent fait la promotion de vos logements avec son lien. Quand un client réserve par ce lien, sa part (réglée dans chaque logement) lui est versée au début du séjour.",
                    ton = TonPuce.Teal, icone = Icons.Outlined.Info,
                )

                // Part marketing de chaque logement
                Carte {
                    Column {
                        TitreSection("Part de l’agent par logement")
                        page.logements.forEach { l ->
                            Separateur(Modifier.padding(vertical = 4.dp))
                            Row(Modifier.fillMaxWidth().padding(vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                                Text(l.titre, style = MaterialTheme.typography.bodyMedium, color = Vivi.Ink, modifier = Modifier.weight(1f))
                                Puce(l.part?.libelle ?: "Aucune", if (l.part != null) TonPuce.Succes else TonPuce.Neutre)
                            }
                        }
                        Text("Pour la changer : Mes logements › Modifier › Agent marketing.", style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(top = 6.dp))
                    }
                }

                TitreSection("Classement")
                Segmente(listOf("mois" to "Ce mois", "annee" to "Cette année", "tout" to "Depuis le début"), vm.periode, vm::periode)
                if (page.classement.isEmpty()) {
                    EtatVide(Icons.Outlined.Campaign, "Aucun agent pour l’instant", "Désignez une personne de confiance : elle reçoit un message pour obtenir ses liens.")
                }
                page.classement.forEach { l ->
                    Carte(remplissage = PaddingValues(14.dp)) {
                        Column {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Box(Modifier.size(32.dp).clip(CircleShape).background(if (l.rang == 1) Vivi.Orange400 else Vivi.Teal50), contentAlignment = Alignment.Center) {
                                    Text("${l.rang}", fontWeight = FontWeight.Bold, color = if (l.rang == 1) Vivi.Ink else Vivi.Teal800, fontSize = 14.sp)
                                }
                                Spacer(Modifier.width(10.dp))
                                Avatar(l.agent.nom.split(' ').mapNotNull { it.firstOrNull() }.joinToString(""), taille = 40.dp)
                                Spacer(Modifier.width(10.dp))
                                Column(Modifier.weight(1f)) {
                                    Text(l.agent.nom, style = MaterialTheme.typography.titleMedium)
                                    Text(l.agent.telephone ?: l.agent.email.orEmpty(), style = MaterialTheme.typography.bodySmall)
                                }
                            }
                            Spacer(Modifier.height(10.dp))
                            Row {
                                Stat("Séjours", l.sejours.toString(), Modifier.weight(1f))
                                Stat("À venir", l.aVenir.toString(), Modifier.weight(1f))
                                Stat("Location", Fmt.fcfa(l.chiffreLocation), Modifier.weight(1.3f))
                                Stat("Ses gains", Fmt.fcfa(l.gains), Modifier.weight(1.3f))
                            }
                            Spacer(Modifier.height(6.dp))
                            Text("Promeut : " + l.logements.joinToString(", ") { it.titre }.ifEmpty { "aucun logement" }, style = MaterialTheme.typography.bodySmall)
                            BoutonTexte("Logements promus", { edition = l }, icone = Icons.Outlined.Edit)
                        }
                    }
                }
                Spacer(Modifier.height(72.dp))
            }
        }
    }

    val page = vm.etat.donnees
    if (designer && page != null) {
        FeuilleAgent(
            titre = "Désigner un agent", page = page, initiaux = page.logements.map { it.id }.toSet(), avecIdentite = true, vm = vm,
            onFermer = { designer = false },
        ) { nom, identifiant, choix -> vm.designer(nom, identifiant, choix) { designer = false } }
    }
    edition?.let { l ->
        if (page != null) FeuilleAgent(
            titre = "Logements promus par ${l.agent.nom}", page = page, initiaux = l.logements.map { it.id }.toSet(), avecIdentite = false, vm = vm,
            onFermer = { edition = null },
        ) { _, _, choix -> vm.modifier(l.agent.id, choix) { edition = null } }
    }
}

@Composable
private fun Stat(libelle: String, valeur: String, modifier: Modifier = Modifier) {
    Column(modifier) {
        Text(libelle, style = MaterialTheme.typography.bodySmall)
        Text(valeur, style = MaterialTheme.typography.titleSmall, color = Vivi.Ink, maxLines = 1)
    }
}

@Composable
private fun FeuilleAgent(
    titre: String,
    page: AgentsPage,
    initiaux: Set<Long>,
    avecIdentite: Boolean,
    vm: AgentsProViewModel,
    onFermer: () -> Unit,
    onValider: (nom: String, identifiant: String, logements: Set<Long>) -> Unit,
) {
    var nom by remember { mutableStateOf("") }
    var identifiant by remember { mutableStateOf("") }
    var choix by remember { mutableStateOf(initiaux) }
    ModalBottomSheet(onDismissRequest = onFermer, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Vivi.White) {
        Column(Modifier.navigationBarsPadding().verticalScroll(rememberScrollState()).padding(horizontal = 20.dp).padding(bottom = 20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(titre, style = MaterialTheme.typography.headlineSmall)
            if (avecIdentite) {
                ChampTexte(nom, { nom = it }, "Nom et prénom", erreur = vm.erreurs["nom"])
                ChampTexte(identifiant, { identifiant = it }, "Téléphone ou email",
                    aide = "Il se connecte à VIVI avec ce numéro ou cette adresse. Un client existant garde son compte.", erreur = vm.erreurs["identifiant"])
            }
            Text("Logements à promouvoir", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
            page.logements.forEach { l ->
                val coche = l.id in choix
                Row(
                    Modifier.fillMaxWidth().heightIn(min = 48.dp).toggleable(coche, role = Role.Checkbox) { choix = if (it) choix + l.id else choix - l.id },
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Checkbox(coche, null, colors = CheckboxDefaults.colors(checkedColor = Vivi.Teal700))
                    Spacer(Modifier.width(8.dp))
                    Column(Modifier.weight(1f)) {
                        Text(l.titre, style = MaterialTheme.typography.bodyLarge, color = Vivi.Ink)
                        Text(l.part?.libelle?.let { "Part : $it" } ?: "Aucune part fixée", style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
            vm.erreurs["logements"]?.let { Text(it, style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Danger)) }
            if (!avecIdentite && choix.isEmpty()) {
                Text("Aucun logement coché : l’agent ne fera plus votre promotion. Ses commissions déjà gagnées lui restent.", style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Danger))
            }
            BoutonPrincipal(
                if (avecIdentite) "Désigner cet agent" else if (choix.isEmpty()) "Retirer cet agent" else "Enregistrer",
                { onValider(nom, identifiant, choix) }, Modifier.fillMaxWidth(),
                actif = if (avecIdentite) nom.trim().length >= 3 && identifiant.isNotBlank() && choix.isNotEmpty() else true,
                enCours = vm.enCours,
            )
        }
    }
}
