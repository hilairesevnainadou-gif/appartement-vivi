@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)

package bj.vivi.app.ui.pro

import bj.vivi.app.ui.donnees
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AccountBalanceWallet
import androidx.compose.material.icons.outlined.Apartment
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.PersonAdd
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material.icons.outlined.SupervisorAccount
import androidx.compose.material.icons.outlined.WorkspacePremium
import androidx.compose.material.icons.outlined.OpenInBrowser
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.Campaign
import androidx.compose.material.icons.outlined.AccountBalance
import androidx.compose.material.icons.outlined.ConfirmationNumber
import androidx.compose.material.icons.outlined.Fingerprint
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.Visibility
import bj.vivi.app.ui.composants.BoutonTexte
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.foundation.layout.widthIn
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.BuildConfig
import bj.vivi.app.data.AbonnementPage
import bj.vivi.app.data.Gestionnaires
import bj.vivi.app.data.LogementsPro
import bj.vivi.app.data.Reseau
import bj.vivi.app.data.Revenus
import bj.vivi.app.data.appel
import bj.vivi.app.data.corps
import bj.vivi.app.ui.AvecEtat
import bj.vivi.app.ui.EcranViewModel
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.Messages
import bj.vivi.app.ui.compte.LigneCompte
import bj.vivi.app.ui.composants.Avatar
import bj.vivi.app.ui.composants.Bandeau
import bj.vivi.app.ui.composants.BarreTitre
import bj.vivi.app.ui.composants.BoutonContour
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.BoutonSecondaire
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.ChampTexte
import bj.vivi.app.ui.composants.EtatVide
import bj.vivi.app.ui.composants.Marque
import bj.vivi.app.ui.composants.PhotoLogement
import bj.vivi.app.ui.composants.Puce
import bj.vivi.app.ui.composants.PuceStatut
import bj.vivi.app.ui.composants.Separateur
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.ouvrirPage
import bj.vivi.app.ui.theme.Vivi
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonPrimitive

/* ======================================================================
 * Menu « Plus » de l'espace pro (pro-bottomnav du site)
 * ====================================================================== */

@Composable
fun EcranPlus(
    onRevenus: () -> Unit,
    onLogements: () -> Unit,
    onAbonnement: () -> Unit,
    onGestionnaires: () -> Unit,
    onAgents: () -> Unit,
    onEspaceAgent: () -> Unit,
    onMesSejours: () -> Unit,
    onCompteRetrait: () -> Unit,
    onExplorer: () -> Unit,
    onNotifications: () -> Unit,
    onCompte: () -> Unit,
) {
    val u by Reseau.session.utilisateur.collectAsState()
    val proprietaire = u?.estProprietaire == true
    Column(Modifier.fillMaxSize().background(Vivi.Sand).verticalScroll(rememberScrollState())) {
        Column(Modifier.fillMaxWidth().background(Vivi.Teal900).statusBarsPadding().padding(16.dp)) {
            // Logo à gauche, face à l'avatar du profil à droite ; l'avatar ouvre « Mon compte »
            // Nom et rôle sous l'avatar, centrés sous l’avatar
            Row(verticalAlignment = Alignment.Top) {
                Marque(blanc = true, taille = 28.dp, modifier = Modifier.padding(top = 10.dp))   // centré sur l'avatar (48 dp)
                Spacer(Modifier.weight(1f))
                Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.widthIn(max = 180.dp)) {
                    bj.vivi.app.ui.composants.AvatarProfil(u != null, u?.nom, onCompte)
                    Text(u?.nom.orEmpty(), style = MaterialTheme.typography.titleSmall.copy(color = Vivi.White), maxLines = 1,
                        overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis, textAlign = androidx.compose.ui.text.style.TextAlign.Center)
                    Text(u?.libelleRole.orEmpty(), style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Teal200), maxLines = 1,
                        textAlign = androidx.compose.ui.text.style.TextAlign.Center)
                }
            }
        }
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
            if (proprietaire) {
                Carte(remplissage = PaddingValues(vertical = 4.dp)) {
                    Column {
                        LigneCompte(Icons.Outlined.AccountBalanceWallet, "Revenus et retraits", "Solde, mouvements, demandes de retrait", null, onRevenus)
                        Separateur(Modifier.padding(start = 56.dp))
                        LigneCompte(Icons.Outlined.AccountBalance, "Compte de retrait", "Numéro Mobile Money ou compte bancaire", null, onCompteRetrait)
                        Separateur(Modifier.padding(start = 56.dp))
                        LigneCompte(Icons.Outlined.Apartment, "Mes logements et salles", "Ajouter, modifier, photos, mise en ligne", null, onLogements)
                        Separateur(Modifier.padding(start = 56.dp))
                        LigneCompte(Icons.Outlined.WorkspacePremium, "Abonnement", "Formule, paiement, essai gratuit", null, onAbonnement)
                        Separateur(Modifier.padding(start = 56.dp))
                        LigneCompte(Icons.Outlined.SupervisorAccount, "Gestionnaires", "Confier l’accueil de vos logements", null, onGestionnaires)
                        Separateur(Modifier.padding(start = 56.dp))
                        LigneCompte(Icons.Outlined.Campaign, "Agents marketing", "Classement, désignation, logements promus", null, onAgents)
                    }
                }
            } else {
                Bandeau("Vous gérez les logements qu’un propriétaire vous a confiés : accueil, calendrier et réservations. Les revenus restent visibles par le propriétaire seulement.",
                    ton = TonPuce.Teal, icone = Icons.Outlined.Info)
            }
            if (u?.estAgent == true) {
                Carte(remplissage = PaddingValues(vertical = 4.dp)) {
                    LigneCompte(Icons.Outlined.Campaign, "Mon espace agent marketing", "Liens à partager, commissions, retraits", null, onEspaceAgent)
                }
            }
            Carte(remplissage = PaddingValues(vertical = 4.dp)) {
                Column {
                    LigneCompte(Icons.Outlined.Notifications, "Notifications", null, null, onNotifications)
                    Separateur(Modifier.padding(start = 56.dp))
                    LigneCompte(Icons.Outlined.Search, "Explorer logements et salles", "Réserver comme un client", null, onExplorer)
                    Separateur(Modifier.padding(start = 56.dp))
                    LigneCompte(Icons.Outlined.ConfirmationNumber, "Mes séjours", "Les réservations que vous avez faites vous-même", null, onMesSejours)
                    Separateur(Modifier.padding(start = 56.dp))
                    LigneCompte(Icons.Outlined.Person, "Mon compte", "Coordonnées, aide, déconnexion", null, onCompte)
                }
            }
            Text("VIVI ${BuildConfig.VERSION_NAME}", style = MaterialTheme.typography.bodySmall, modifier = Modifier.align(Alignment.CenterHorizontally))
        }
    }
}

/* ======================================================================
 * Revenus et retraits (propriétaire)
 * ====================================================================== */

class RevenusViewModel : EcranViewModel<Revenus>() {
    var compte by mutableStateOf<bj.vivi.app.data.CompteRetrait?>(null)
        private set
    init { recharger() }
    override suspend fun charger() = appel { revenus() }.map { it.data }.onSuccess {
        appel { compteRetrait() }.onSuccess { compte = it.data }
    }

    fun retirer(montant: Int?, fait: () -> Unit) = action {
        appel { demanderRetrait(corps("montant" to montant)) }.map { Messages.afficher(it.message ?: "Demande de retrait envoyée."); fait(); recharger(silencieux = true) }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranRevenus(onRetour: () -> Unit, onCompteRetrait: () -> Unit = {}, vm: RevenusViewModel = viewModel()) {
    var retrait by remember { mutableStateOf(false) }
    val context = LocalContext.current
    // Retour de l'écran « Compte de retrait » : compte relu
    androidx.lifecycle.compose.LifecycleEventEffect(androidx.lifecycle.Lifecycle.Event.ON_RESUME) { if (vm.etat.donnees != null) vm.recharger(silencieux = true) }
    Scaffold(containerColor = Vivi.Sand, topBar = { BarreTitre("Revenus et retraits", onRetour) }) { marges ->
        AvecEtat(vm, Modifier.padding(marges), squelette = { bj.vivi.app.ui.composants.SqueletteDetail(it) }) { r ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                Carte(fond = Vivi.Teal900) {
                    Column {
                        Text("Solde disponible", style = MaterialTheme.typography.bodyMedium.copy(color = Vivi.Teal200))
                        Text(Fmt.fcfa(r.solde), style = MaterialTheme.typography.displaySmall.copy(color = Vivi.White))
                        Text("Gagnés ce mois-ci : ${Fmt.fcfa(r.ceMois)}", style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Teal100))
                        Spacer(Modifier.height(16.dp))
                        bj.vivi.app.ui.compte.LigneCompteRetrait(vm.compte, onCompteRetrait, clair = true)
                        Spacer(Modifier.height(10.dp))
                        BoutonPrincipal("Demander un retrait", { if (vm.compte?.configure == true) retrait = true else onCompteRetrait() }, Modifier.fillMaxWidth(), actif = r.solde >= r.retraitMinimum)
                        if (r.solde < r.retraitMinimum) Text("Retrait possible à partir de ${Fmt.fcfa(r.retraitMinimum)}.", style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Teal100), modifier = Modifier.padding(top = 6.dp))
                    }
                }
                if (r.retraits.isNotEmpty()) {
                    Carte {
                        Column {
                            TitreSection("Mes demandes de retrait")
                            r.retraits.forEach { d ->
                                Separateur(Modifier.padding(vertical = 4.dp))
                                Row(Modifier.fillMaxWidth().padding(vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Column(Modifier.weight(1f)) {
                                        Text(Fmt.fcfa(d.montant), style = MaterialTheme.typography.titleSmall)
                                        Text("${d.reference} · ${Fmt.numerique(d.le)}" + (d.modeReglement?.let { " · $it" } ?: ""), style = MaterialTheme.typography.bodySmall)
                                        d.compte?.numero?.let { Text("Sur ${d.compte.moyen.orEmpty()} · ${bj.vivi.app.data.numeroLisible(it)}", style = MaterialTheme.typography.bodySmall) }
                                        d.note?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                                    }
                                    PuceStatut(d.statut)
                                }
                            }
                        }
                    }
                }
                Carte {
                    Column {
                        TitreSection("Mouvements du solde")
                        if (r.mouvements.isEmpty()) Text("Aucun mouvement pour l’instant.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted, modifier = Modifier.padding(vertical = 8.dp))
                        r.mouvements.forEach { m ->
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
    val r = vm.etat.donnees
    if (retrait && r != null) {
        var montant by remember { mutableStateOf(r.solde.toString()) }
        ModalBottomSheet(onDismissRequest = { if (!vm.enCours) retrait = false }, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Vivi.White) {
            Column(Modifier.padding(horizontal = 20.dp).padding(bottom = 24.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Text("Demander un retrait", style = MaterialTheme.typography.headlineSmall)
                ChampTexte(montant, { montant = it.filter(Char::isDigit).take(9) }, "Montant (FCFA)", clavier = KeyboardType.Number,
                    aide = "Entre ${Fmt.fcfa(r.retraitMinimum)} et ${Fmt.fcfa(r.solde)}. Payé par une caissière VIVI sur ${vm.compte?.resume.orEmpty()}.")
                val m = montant.toIntOrNull() ?: 0
                BoutonPrincipal(if (vm.enCours) "Envoi de la demande…" else "Envoyer la demande", { vm.retirer(m) { retrait = false } }, Modifier.fillMaxWidth(), actif = m in r.retraitMinimum..r.solde, enCours = vm.enCours)
            }
        }
    }
}

/* ======================================================================
 * Mes logements : statut, mise en ligne et en pause (pro-logements.html)
 * ====================================================================== */

class LogementsProViewModel : EcranViewModel<LogementsPro>() {
    init { recharger() }
    override suspend fun charger() = appel { mesLogements() }

    fun enLigne(id: Long, actif: Boolean) = action {
        appel { mettreEnLigne(id, corps("en_ligne" to actif)) }.map { rep ->
            etat.donnees?.let { p -> remplacer(p.copy(data = p.data.map { if (it.id == id) rep.data else it })) }
            Messages.afficher(rep.message ?: "")
        }
    }
}

@Composable
fun EcranLogementsPro(
    onRetour: () -> Unit,
    onApercu: (String) -> Unit,
    onAjouter: () -> Unit,
    onModifier: (Long) -> Unit,
    vm: LogementsProViewModel = viewModel(),
) {
    // Retour de la fiche d'édition : la liste est relue (statut, photo principale)
    androidx.lifecycle.compose.LifecycleEventEffect(androidx.lifecycle.Lifecycle.Event.ON_RESUME) { if (vm.etat.donnees != null) vm.recharger(silencieux = true) }
    Scaffold(
        containerColor = Vivi.Sand,
        topBar = { BarreTitre("Mes logements et salles", onRetour) },
        floatingActionButton = {
            androidx.compose.material3.ExtendedFloatingActionButton(
                onClick = onAjouter, containerColor = Vivi.Orange400, contentColor = Vivi.Ink,
                icon = { Icon(Icons.Outlined.Add, null) }, text = { Text("Ajouter un logement", fontWeight = FontWeight.SemiBold, color = Vivi.Ink) },
            )
        },
    ) { marges ->
        AvecEtat(vm, Modifier.padding(marges)) { p ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                p.quota?.let { q ->
                    Bandeau(
                        if (q.formule == null) "Aucun abonnement actif : vos logements ne peuvent pas être publiés."
                        else "Formule ${q.formule} : ${q.utilises} logement${if (q.utilises > 1) "s" else ""} sur ${q.max ?: "∞"}" +
                            (q.salles?.let { s -> " · ${s.utilises} salle${if (s.utilises > 1) "s" else ""} sur ${s.max ?: "∞"}" } ?: "") + ".",
                        ton = if (q.formule == null) TonPuce.Orange else TonPuce.Teal, icone = Icons.Outlined.WorkspacePremium,
                    )
                }
                if (p.data.isEmpty()) EtatVide(Icons.Outlined.Apartment, "Aucun logement", "Ajoutez votre premier logement : informations, tarifs, créneaux et photos.") {
                    BoutonPrincipal("Ajouter un logement", onAjouter, icone = Icons.Outlined.Add)
                }
                p.data.forEach { l ->
                    Carte(remplissage = PaddingValues(12.dp), onClick = { onModifier(l.id) }) {
                        Column {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                PhotoLogement(l.photo, null, Modifier.size(72.dp).clip(RoundedCornerShape(12.dp)))
                                Spacer(Modifier.width(12.dp))
                                Column(Modifier.weight(1f)) {
                                    PuceStatut(l.statut)
                                    Text(l.titre, style = MaterialTheme.typography.titleMedium, maxLines = 2)
                                    Text(listOfNotNull(l.quartier, l.ville).joinToString(", "), style = MaterialTheme.typography.bodySmall)
                                    if (l.gereParVivi) Text(
                                        "Géré par VIVI" + (l.gerantVivi?.nom?.let { " · $it" } ?: " · gérant à désigner"),
                                        style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Teal700),
                                    )
                                }
                            }
                            l.motifRefus?.let { Spacer(Modifier.height(8.dp)); Bandeau("Refusé : $it", ton = TonPuce.Danger) }
                            Row(Modifier.padding(top = 4.dp)) {
                                BoutonTexte("Modifier", { onModifier(l.id) }, icone = Icons.Outlined.Edit)
                                if (l.statut.valeur == "publie") BoutonTexte("Voir la fiche", { onApercu(l.slug) }, icone = Icons.Outlined.Visibility)
                            }
                            if (l.statut.valeur == "publie") {
                                Separateur(Modifier.padding(vertical = 8.dp))
                                Row(
                                    Modifier.fillMaxWidth().heightIn(min = 48.dp).toggleable(l.enLigne, enabled = !vm.enCours, role = Role.Switch) { vm.enLigne(l.id, it) },
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    Column(Modifier.weight(1f)) {
                                        Text(if (l.enLigne) "En ligne" else "En pause", style = MaterialTheme.typography.titleSmall)
                                        Text(if (l.enLigne) "Visible dans la recherche" else "Masqué de la recherche", style = MaterialTheme.typography.bodySmall)
                                    }
                                    Switch(l.enLigne, null, colors = SwitchDefaults.colors(checkedTrackColor = Vivi.Teal700))
                                }
                            }
                        }
                    }
                }
                Spacer(Modifier.height(72.dp))   // place pour le bouton « Ajouter un logement »
            }
        }
    }
}


/* ======================================================================
 * Abonnement (pro-abonnement.html)
 * ====================================================================== */

class AbonnementViewModel : EcranViewModel<AbonnementPage>() {
    init { recharger() }
    override suspend fun charger() = appel { abonnement() }.map { it.data }

    fun souscrire(formule: Long, surPaiement: (String, Long) -> Unit) = action {
        appel { souscrire(formule) }.map {
            bj.vivi.app.ui.client.PaiementsLances.noter(it.data.paiement.id, it.data.urlPaiement)
            surPaiement(it.data.urlPaiement, it.data.paiement.id)
        }
    }

    fun essai(formule: Long) = action {
        appel { essai(formule) }.map { Messages.afficher(it.message ?: "Essai gratuit commencé."); recharger(silencieux = true) }
    }
}

@Composable
fun EcranAbonnement(onRetour: () -> Unit, onPaiement: (Long) -> Unit, vm: AbonnementViewModel = viewModel()) {
    val context = LocalContext.current
    Scaffold(containerColor = Vivi.Sand, topBar = { BarreTitre("Abonnement", onRetour) }) { marges ->
        AvecEtat(vm, Modifier.padding(marges), squelette = { bj.vivi.app.ui.composants.SqueletteDetail(it) }) { a ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                val actif = a.actif
                Carte(fond = if (actif != null) Vivi.Teal900 else Vivi.Orange50) {
                    Column {
                        if (actif != null) {
                            Text("Formule en cours", style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Teal200))
                            Text(actif.formule + if (actif.essai) " · essai" else "", style = MaterialTheme.typography.headlineSmall.copy(color = Vivi.White))
                            Text("Jusqu’au ${Fmt.jourLong(actif.fin)} · ${a.logementsUtilises} logement(s) utilisé(s)", style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Teal100))
                            a.suivant?.let { s -> Text("Ensuite : ${s.formule} à partir du ${Fmt.jourLong(s.debut)}", style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Teal100)) }
                        } else {
                            Text("Aucun abonnement actif", style = MaterialTheme.typography.titleMedium.copy(color = Vivi.OrangeInk))
                            Text("Choisissez une formule pour publier vos logements.", style = MaterialTheme.typography.bodyMedium.copy(color = Vivi.OrangeInk))
                        }
                    }
                }
                a.promotion?.let { p -> Bandeau("${p.libelle} : −${p.pourcentage} %" + (p.fin?.let { " jusqu’au ${Fmt.jourLong(it)}" } ?: ""), ton = TonPuce.Orange, icone = Icons.Outlined.WorkspacePremium) }

                a.formules.forEach { f ->
                    val enCours = actif?.formuleId == f.id
                    Carte {
                        Column {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text(f.nom, style = MaterialTheme.typography.titleLarge, modifier = Modifier.weight(1f))
                                if (enCours) Puce("Votre formule", TonPuce.Succes)
                            }
                            Row(verticalAlignment = Alignment.Bottom) {
                                Text(Fmt.fcfa(f.tarif.net), style = MaterialTheme.typography.headlineSmall)
                                Text(" / mois", style = MaterialTheme.typography.bodySmall)
                                if (f.tarif.remise > 0) {
                                    Spacer(Modifier.width(8.dp))
                                    Text(Fmt.fcfa(f.tarif.prix), style = MaterialTheme.typography.bodySmall, textDecoration = TextDecoration.LineThrough)
                                }
                            }
                            Spacer(Modifier.height(8.dp))
                            Avantage("${if (f.logementsMax > 0) f.logementsMax else "Logements illimités"}${if (f.logementsMax > 0) " logement" + if (f.logementsMax > 1) "s" else "" else ""}")
                            f.sallesMax?.let { s -> if (s > 0) Avantage("$s salle" + if (s > 1) "s" else "") }
                            Avantage(if (f.gestionnairesMax > 0) "${f.gestionnairesMax} gestionnaire${if (f.gestionnairesMax > 1) "s" else ""}" else "Sans gestionnaire")
                            (f.avantages as? JsonArray)?.forEach { (it as? JsonPrimitive)?.content?.let { t -> Avantage(t) } }
                            Spacer(Modifier.height(12.dp))
                            BoutonPrincipal(if (enCours) "Renouveler" else "Choisir cette formule", {
                                vm.souscrire(f.id) { _, id -> onPaiement(id) }   // page FedaPay ouverte dans l'application
                            }, Modifier.fillMaxWidth(), enCours = vm.enCours)
                            if (a.essai.possible && (a.essai.formuleId == null || a.essai.formuleId == f.id)) {
                                Spacer(Modifier.height(8.dp))
                                BoutonContour("Essai gratuit de ${a.essai.jours} jours", { vm.essai(f.id) }, Modifier.fillMaxWidth())
                            }
                        }
                    }
                }

                if (a.paiements.isNotEmpty()) {
                    Carte {
                        Column {
                            TitreSection("Paiements")
                            a.paiements.forEach { p ->
                                Separateur(Modifier.padding(vertical = 4.dp))
                                Row(Modifier.fillMaxWidth().padding(vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Column(Modifier.weight(1f)) {
                                        Text("${p.formule ?: "Abonnement"} · ${Fmt.fcfa(p.montant)}", style = MaterialTheme.typography.bodyMedium, color = Vivi.Ink)
                                        Text(Fmt.numerique(p.payeLe) + (p.moyen?.let { " · $it" } ?: ""), style = MaterialTheme.typography.bodySmall)
                                    }
                                    PuceStatut(p.statut)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun Avantage(texte: String) {
    Row(Modifier.padding(vertical = 3.dp), verticalAlignment = Alignment.CenterVertically) {
        Icon(Icons.Outlined.CheckCircle, null, tint = Vivi.Teal700, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(8.dp))
        Text(texte, style = MaterialTheme.typography.bodyMedium)
    }
}

/* ======================================================================
 * Gestionnaires : confier l'accueil de ses logements
 * ====================================================================== */

class GestionnairesViewModel : EcranViewModel<Gestionnaires>() {
    init { recharger() }
    override suspend fun charger() = appel { gestionnaires() }.map { it.data }

    fun inviter(nom: String, identifiant: String, logements: Set<Long>, fait: () -> Unit) = action {
        appel { confierGestion(corps("nom" to nom.trim(), "identifiant" to identifiant.trim(), "logements" to logements.toList())) }
            .map { Messages.afficher(it.message ?: "Gestionnaire ajouté."); fait(); recharger(silencieux = true) }
    }

    fun modifier(id: Long, logements: Set<Long>, fait: () -> Unit) = action {
        appel { modifierGestionnaire(id, corps("logements" to logements.toList())) }
            .map { Messages.afficher(it.message ?: "Accès mis à jour."); fait(); recharger(silencieux = true) }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranGestionnaires(onRetour: () -> Unit, vm: GestionnairesViewModel = viewModel()) {
    var edition by remember { mutableStateOf<Gestionnaires.Gestionnaire?>(null) }
    var nouveau by remember { mutableStateOf(false) }
    Scaffold(containerColor = Vivi.Sand, topBar = { BarreTitre("Gestionnaires", onRetour) }) { marges ->
        AvecEtat(vm, Modifier.padding(marges)) { g ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Bandeau("Un gestionnaire accueille vos clients (scanner, calendrier, réservations) sur les logements que vous lui confiez. Il ne voit pas vos revenus. " +
                    "Votre formule en permet ${g.maximum}.", ton = TonPuce.Teal, icone = Icons.Outlined.Info)
                if (g.gestionnaires.isEmpty()) EtatVide(Icons.Outlined.SupervisorAccount, "Aucun gestionnaire")
                g.gestionnaires.forEach { p ->
                    Carte(onClick = { edition = p }) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Avatar(p.nom.split(" ").mapNotNull { it.firstOrNull() }.joinToString("").take(2))
                            Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) {
                                Text(p.nom, style = MaterialTheme.typography.titleSmall)
                                Text(p.telephone ?: p.email.orEmpty(), style = MaterialTheme.typography.bodySmall)
                                Text(if (p.logements.isEmpty()) "Aucun logement confié" else p.logements.joinToString(", ") { it.titre }, style = MaterialTheme.typography.bodySmall, maxLines = 2)
                            }
                        }
                    }
                }
                BoutonSecondaire("Ajouter un gestionnaire", { nouveau = true }, Modifier.fillMaxWidth(), icone = Icons.Outlined.PersonAdd,
                    actif = g.gestionnaires.size < g.maximum && g.logements.isNotEmpty())
            }
        }
    }
    val g = vm.etat.donnees ?: return
    if (nouveau || edition != null) {
        val existant = edition
        var nom by remember(existant) { mutableStateOf(existant?.nom.orEmpty()) }
        var identifiant by remember(existant) { mutableStateOf("") }
        var choix by remember(existant) { mutableStateOf(existant?.logements?.map { it.id }?.toSet() ?: emptySet()) }
        val fermer = { nouveau = false; edition = null }
        ModalBottomSheet(onDismissRequest = fermer, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Vivi.White) {
            Column(Modifier.padding(horizontal = 20.dp).padding(bottom = 24.dp).verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(existant?.nom ?: "Nouveau gestionnaire", style = MaterialTheme.typography.headlineSmall)
                if (existant == null) {
                    ChampTexte(nom, { nom = it }, "Nom et prénom")
                    ChampTexte(identifiant, { identifiant = it }, "Email ou téléphone", clavier = KeyboardType.Email, aide = "Il se connectera avec cet identifiant.")
                }
                Text("Logements confiés", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
                g.logements.forEach { l ->
                    val coche = l.id in choix
                    Row(
                        Modifier.fillMaxWidth().heightIn(min = 48.dp).toggleable(coche, role = Role.Checkbox) { choix = if (it) choix + l.id else choix - l.id },
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Checkbox(coche, null, colors = CheckboxDefaults.colors(checkedColor = Vivi.Teal700))
                        Spacer(Modifier.width(8.dp))
                        Text(l.titre, style = MaterialTheme.typography.bodyLarge)
                    }
                }
                if (existant == null) {
                    BoutonPrincipal("Inviter", { vm.inviter(nom, identifiant, choix, fermer) }, Modifier.fillMaxWidth(),
                        actif = nom.trim().length >= 3 && identifiant.isNotBlank() && choix.isNotEmpty(), enCours = vm.enCours)
                } else {
                    BoutonPrincipal("Enregistrer", { vm.modifier(existant.id, choix, fermer) }, Modifier.fillMaxWidth(), enCours = vm.enCours)
                    BoutonContour("Retirer l’accès", { vm.modifier(existant.id, emptySet(), fermer) }, Modifier.fillMaxWidth(), danger = true)
                }
            }
        }
    }
}
