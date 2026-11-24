@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)

package bj.vivi.app.ui.pro

import bj.vivi.app.ui.donnees
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.outlined.KeyboardArrowRight
import androidx.compose.material.icons.outlined.Block
import androidx.compose.material.icons.outlined.Call
import androidx.compose.material.icons.outlined.EventBusy
import androidx.compose.material.icons.outlined.EventRepeat
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.LockOpen
import androidx.compose.material.icons.outlined.Payments
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.automirrored.outlined.Login
import androidx.compose.material.icons.automirrored.outlined.Logout
import androidx.compose.material.icons.automirrored.outlined.Chat
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.imePadding
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.PrimaryScrollableTabRow
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRowDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.Calendrier
import bj.vivi.app.data.LogementGere
import bj.vivi.app.data.OptionsDeplacement
import bj.vivi.app.data.Reservation
import bj.vivi.app.data.appel
import bj.vivi.app.data.corps
import bj.vivi.app.ui.AvecEtat
import bj.vivi.app.ui.EcranViewModel
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.Messages
import bj.vivi.app.ui.appeler
import bj.vivi.app.ui.client.CaseChoix
import bj.vivi.app.ui.client.ChoixDate
import bj.vivi.app.ui.composants.Bandeau
import bj.vivi.app.ui.composants.BarreTitre
import bj.vivi.app.ui.composants.BoutonContour
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.BoutonSecondaire
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.ChampTexte
import bj.vivi.app.ui.composants.EtatVide
import bj.vivi.app.ui.composants.LigneMontant
import bj.vivi.app.ui.composants.Puce
import bj.vivi.app.ui.composants.PuceStatut
import bj.vivi.app.ui.composants.Separateur
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.pluriel
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.Job
import kotlinx.coroutines.launch
import java.time.DayOfWeek
import java.time.LocalDate
import java.time.YearMonth

/* ======================================================================
 * Réservations des logements gérés (propriétaire et gestionnaire) :
 * à venir, passées, annulées ; groupées par jour ; ajout d'un client venu hors plateforme.
 * ====================================================================== */

data class PageReservations(val liste: List<Reservation>, val compteurs: Map<String, Int>, val pageMax: Int = 1)

class ReservationsProViewModel : EcranViewModel<PageReservations>() {
    var onglet by mutableStateOf("avenir")
        private set
    var logement by mutableStateOf<Long?>(null)
        private set
    var logements by mutableStateOf<List<LogementGere>>(emptyList())
        private set
    var suiteEnCours by mutableStateOf(false)
        private set
    var erreursAjout by mutableStateOf<Map<String, String>>(emptyMap())
        private set
    private var page = 1

    init {
        recharger()
        viewModelScope.launch { appel { logementsGeres() }.onSuccess { logements = it.data } }
    }

    override suspend fun charger(): Result<PageReservations> {
        page = 1
        return appel { reservationsPro(onglet, logement, 1) }.map { PageReservations(it.data, it.meta?.compteurs.orEmpty(), it.meta?.pageMax ?: 1) }
    }

    fun choisir(o: String = onglet, l: Long? = logement) {
        onglet = o
        logement = l
        recharger()
    }

    /** Page suivante quand on arrive en bas de la liste. */
    fun suite() {
        val p = etat.donnees ?: return
        if (suiteEnCours || page >= p.pageMax) return
        viewModelScope.launch {
            suiteEnCours = true
            appel { reservationsPro(onglet, logement, page + 1) }.onSuccess { r ->
                page++
                remplacer(p.copy(liste = p.liste + r.data))
            }
            suiteEnCours = false
        }
    }

    fun oublierErreursAjout() { erreursAjout = emptyMap() }

    /** Client venu hors de la plateforme : la période n'est plus proposée en ligne. */
    fun ajouter(logementId: Long, donnees: Map<String, Any?>, fait: (String) -> Unit) {
        erreursAjout = emptyMap()
        action(surErreur = { e -> erreursAjout = e.champs.ifEmpty { mapOf("reservation" to e.message) } }) {
            appel { ajouterReservation(logementId, corps(*donnees.toList().toTypedArray())) }.map { r ->
                Messages.afficher(r.message ?: "Réservation ajoutée.")
                recharger(silencieux = true)
                fait(r.data.reference)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranReservationsPro(onReservation: (String) -> Unit, vm: ReservationsProViewModel = viewModel()) {
    val onglets = listOf("avenir" to "À venir", "passees" to "Passées", "annulees" to "Annulées")
    val compteurs = vm.etat.donnees?.compteurs.orEmpty()
    var ajout by remember { mutableStateOf(false) }
    // Retour du détail (encaissement, déplacement) : liste relue sans écran de chargement
    androidx.lifecycle.compose.LifecycleEventEffect(androidx.lifecycle.Lifecycle.Event.ON_RESUME) { if (vm.etat.donnees != null) vm.recharger(silencieux = true) }

    Scaffold(
        containerColor = Vivi.Sand,
        topBar = {
            Column(Modifier.background(Vivi.White)) {
                BarreTitre("Réservations")
                val i = onglets.indexOfFirst { it.first == vm.onglet }
                PrimaryScrollableTabRow(
                    selectedTabIndex = i, containerColor = Vivi.White, contentColor = Vivi.Teal700, edgePadding = 8.dp,
                    indicator = { TabRowDefaults.PrimaryIndicator(Modifier.tabIndicatorOffset(i), color = Vivi.Teal700, width = 48.dp) },
                ) {
                    onglets.forEach { (cle, texte) ->
                        Tab(selected = vm.onglet == cle, onClick = { vm.choisir(o = cle) }, selectedContentColor = Vivi.Teal700, unselectedContentColor = Vivi.Muted,
                            text = {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Text(texte, fontWeight = FontWeight.SemiBold)
                                    compteurs[cle]?.takeIf { it > 0 }?.let { n ->
                                        Spacer(Modifier.width(6.dp))
                                        Box(Modifier.clip(CircleShape).background(if (vm.onglet == cle) Vivi.Teal700 else Vivi.Segmented).padding(horizontal = 7.dp, vertical = 1.dp)) {
                                            Text("$n", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = if (vm.onglet == cle) Vivi.White else Vivi.Body)
                                        }
                                    }
                                }
                            })
                    }
                }
                if (vm.logements.size > 1) {
                    LazyRow(Modifier.padding(vertical = 8.dp), contentPadding = PaddingValues(horizontal = 16.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        item { PuceFiltre("Tous les logements", vm.logement == null) { vm.choisir(l = null) } }
                        items(vm.logements) { l -> PuceFiltre(l.titre, vm.logement == l.id) { vm.choisir(l = l.id) } }
                    }
                }
            }
        },
        floatingActionButton = {
            if (vm.logements.isNotEmpty()) androidx.compose.material3.ExtendedFloatingActionButton(
                onClick = { vm.oublierErreursAjout(); ajout = true }, containerColor = Vivi.Orange400, contentColor = Vivi.Ink,
                icon = { Icon(Icons.Outlined.Add, null) }, text = { Text("Ajouter", fontWeight = FontWeight.SemiBold, color = Vivi.Ink) },
            )
        },
    ) { marges ->
        AvecEtat(vm, Modifier.padding(marges)) { page ->
            if (page.liste.isEmpty()) {
                Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState())) {
                    EtatVide(
                        Icons.Outlined.CalendarMonth,
                        when (vm.onglet) { "avenir" -> "Aucune réservation à venir"; "passees" -> "Aucune réservation passée"; else -> "Aucune réservation annulée" },
                        if (vm.onglet == "avenir") "Les réservations payées en ligne arrivent ici. Un client venu directement ? Touchez « Ajouter »." else null,
                    )
                }
            } else {
                val liste = androidx.compose.foundation.lazy.rememberLazyListState()
                val bas by remember { androidx.compose.runtime.derivedStateOf { (liste.layoutInfo.visibleItemsInfo.lastOrNull()?.index ?: 0) >= liste.layoutInfo.totalItemsCount - 3 } }
                androidx.compose.runtime.LaunchedEffect(bas) { if (bas) vm.suite() }
                // Regroupement par jour d'arrivée
                val parJour = page.liste.groupBy { Fmt.dateHeure(it.debut)?.toLocalDate() }
                LazyColumn(state = liste, contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 96.dp), modifier = Modifier.fillMaxSize()) {
                    parJour.forEach { (jour, reservations) ->
                        item(key = "jour-$jour") { EnTeteJour(jour, reservations.size) }
                        items(reservations, key = { it.reference }) { r ->
                            CarteReservationPro(r, Modifier.padding(bottom = 10.dp)) { onReservation(r.reference) }
                        }
                    }
                    if (vm.suiteEnCours) item { Box(Modifier.fillMaxWidth().padding(16.dp), contentAlignment = Alignment.Center) { androidx.compose.material3.CircularProgressIndicator(Modifier.size(24.dp), color = Vivi.Teal700, strokeWidth = 3.dp) } }
                }
            }
        }
    }

    if (ajout) FeuilleAjoutReservation(vm, onFermer = { ajout = false }) { ref -> ajout = false; onReservation(ref) }
}

@Composable
private fun EnTeteJour(jour: LocalDate?, nombre: Int) {
    val aujourdhui = Fmt.aujourdhui()
    val titre = when (jour) {
        null -> "Date à préciser"
        aujourdhui -> "Aujourd’hui"
        aujourdhui.plusDays(1) -> "Demain"
        aujourdhui.minusDays(1) -> "Hier"
        else -> Fmt.jourLong(jour)
    }
    Row(Modifier.fillMaxWidth().padding(top = 10.dp, bottom = 8.dp), verticalAlignment = Alignment.CenterVertically) {
        Text(titre, style = MaterialTheme.typography.titleMedium, color = if (jour == aujourdhui) Vivi.Teal800 else Vivi.Ink)
        if (jour != null && jour != aujourdhui && titre != Fmt.jourLong(jour)) {
            Spacer(Modifier.width(6.dp))
            Text(Fmt.jourCourt(jour), style = MaterialTheme.typography.bodySmall)
        }
        Spacer(Modifier.weight(1f))
        Text(pluriel(nombre, "arrivée"), style = MaterialTheme.typography.bodySmall)
    }
}

/** Couleur de la barre latérale d'une réservation, selon son statut. */
private fun couleurStatut(statut: String): Color = when (statut) {
    "confirmee" -> Vivi.Teal700
    "en_cours" -> Vivi.Success
    "en_attente_paiement" -> Vivi.Orange400
    "annulee" -> Vivi.Danger
    else -> Vivi.LineStrong
}

/** Carte d'une réservation : barre de statut, heure, client, logement, formule, reste à payer, appel direct. */
@Composable
private fun CarteReservationPro(r: Reservation, modifier: Modifier = Modifier, onClick: () -> Unit) {
    val context = LocalContext.current
    Carte(modifier.fillMaxWidth(), onClick = onClick, remplissage = PaddingValues(0.dp)) {
        Row(Modifier.height(androidx.compose.foundation.layout.IntrinsicSize.Min)) {
            Box(Modifier.width(5.dp).fillMaxHeight().background(couleurStatut(r.statut.valeur)))
            Row(Modifier.weight(1f).padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.width(58.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(Fmt.heure(r.debut), fontFamily = bj.vivi.app.ui.theme.Outfit, fontWeight = FontWeight.Bold, color = Vivi.Ink, fontSize = 18.sp)
                    Text(r.duree, fontSize = 12.sp, color = Vivi.Muted, textAlign = TextAlign.Center, maxLines = 2)
                }
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                    Text(r.client?.nom ?: "Client", style = MaterialTheme.typography.titleSmall, maxLines = 1)
                    Text(r.avecPersonnes(r.logement?.titre.orEmpty()), style = MaterialTheme.typography.bodySmall, maxLines = 1)
                    @OptIn(ExperimentalLayoutApi::class)
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(4.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        PuceStatut(r.statut)
                        if (r.estManuelle) Puce("Hors plateforme", TonPuce.Neutre)
                        if (r.estManuelle && r.resteAPayer > 0) Puce("Reste ${Fmt.fcfa(r.resteAPayer)}", TonPuce.Orange)
                    }
                }
                r.client?.telephone?.let { tel ->
                    IconButton(onClick = { appeler(context, tel) }) {
                        Box(Modifier.size(40.dp).clip(CircleShape).background(Vivi.Teal50), contentAlignment = Alignment.Center) {
                            Icon(Icons.Outlined.Call, contentDescription = "Appeler ${r.client.nom}", tint = Vivi.Teal700, modifier = Modifier.size(20.dp))
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun PuceFiltre(texte: String, choisi: Boolean, onClick: () -> Unit) {
    FilterChip(
        selected = choisi, onClick = onClick, label = { Text(texte, maxLines = 1, color = if (choisi) Vivi.White else Vivi.Body, fontWeight = FontWeight.SemiBold) },
        shape = RoundedCornerShape(999.dp),
        colors = FilterChipDefaults.filterChipColors(selectedContainerColor = Vivi.Teal700, selectedLabelColor = Vivi.White, containerColor = Vivi.White, labelColor = Vivi.Body),
        border = FilterChipDefaults.filterChipBorder(enabled = true, selected = choisi, borderColor = Vivi.LineStrong, selectedBorderColor = Vivi.Teal700),
    )
}

/* ---------- Ajout d'un client venu hors plateforme ---------- */

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun FeuilleAjoutReservation(vm: ReservationsProViewModel, onFermer: () -> Unit, onAjoutee: (String) -> Unit) =
    FeuilleAjoutReservation(vm.logements, vm.logement, Fmt.aujourdhui(), vm.erreursAjout, vm.enCours, onFermer) { id, donnees -> vm.ajouter(id, donnees, onAjoutee) }

/**
 * Réservation d'un client venu hors plateforme, pour un logement ou une salle.
 * Ouverte depuis la liste des réservations ou depuis le calendrier (jour et logement déjà choisis).
 */
@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun FeuilleAjoutReservation(
    logements: List<LogementGere>,
    logementInitial: Long?,
    dateInitiale: LocalDate,
    e: Map<String, String>,
    enCours: Boolean,
    onFermer: () -> Unit,
    onValider: (Long, Map<String, Any?>) -> Unit,
) {
    var logement by remember { mutableStateOf(logements.firstOrNull { it.id == logementInitial } ?: logements.first()) }
    var formule by remember(logement) { mutableStateOf(logement.formules.firstOrNull()?.valeur ?: "heure") }
    var date by remember { mutableStateOf(dateInitiale) }
    var heure by remember { mutableStateOf("14:00") }
    var duree by remember { mutableStateOf(2) }
    var creneau by remember(logement) { mutableStateOf(logement.creneaux.firstOrNull()?.id) }
    var jours by remember { mutableStateOf(1) }
    var voyageurs by remember { mutableStateOf(2) }
    var nom by remember { mutableStateOf("") }
    var telephone by remember { mutableStateOf("") }
    var montant by remember { mutableStateOf("") }
    var encaisse by remember { mutableStateOf("") }
    var note by remember { mutableStateOf("") }
    var choixDate by remember { mutableStateOf(false) }
    val salle = logement.estSalle

    ModalBottomSheet(onDismissRequest = onFermer, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Vivi.White) {
        Column(
            Modifier.navigationBarsPadding().imePadding().verticalScroll(rememberScrollState()).padding(horizontal = 20.dp).padding(bottom = 20.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text("Ajouter une réservation", style = MaterialTheme.typography.headlineSmall)
            Text("Client venu sans passer par VIVI : le créneau est retiré de la réservation en ligne.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)

            if (logements.size > 1) {
                val avecSalles = logements.any { it.estSalle }
                val avecLogements = logements.any { !it.estSalle }
                Text(
                    when { avecSalles && avecLogements -> "Logement ou salle"; avecSalles -> "Salle"; else -> "Logement" },
                    style = MaterialTheme.typography.labelMedium, color = Vivi.Ink,
                )
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    // Logements d’abord, puis les salles
                    logements.sortedBy { it.estSalle }.forEach { l ->
                        PuceFiltre(l.titre, l.id == logement.id) { logement = l }
                    }
                }
            }
            Text("Formule", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                logement.formules.forEach { f -> PuceFiltre(f.libelle, f.valeur == formule) { formule = f.valeur } }
            }
            BoutonContour(Fmt.jourLong(date), { choixDate = true }, Modifier.fillMaxWidth(), icone = Icons.Outlined.CalendarMonth)
            e["date"]?.let { Text(it, style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Danger)) }
            when (formule) {
                "heure" -> {
                    Text(if (salle) "Heure de début" else "Heure d’arrivée", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        (0..23).map { "%02d:00".format(it) }.forEach { h -> CaseChoix(h, choisi = h == heure) { heure = h } }
                    }
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("Durée", style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
                        bj.vivi.app.ui.composants.Compteur(duree, { duree = it }, 1, 24, "durée", suffixe = " h", compact = true)
                    }
                }
                "demi_journee" -> {
                    Text("Créneau", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
                    logement.creneaux.forEach { c ->
                        CaseChoix("${c.nom} · ${c.heureDebut} – ${c.heureFin}", choisi = c.id == creneau, modifier = Modifier.fillMaxWidth()) { creneau = c.id }
                    }
                }
                else -> Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Nombre de jours", style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
                    bj.vivi.app.ui.composants.Compteur(jours, { jours = it }, 1, 30, "jours", compact = true)
                }
            }
            // Voyageurs : seulement pour un logement (une salle se loue entière)
            if (!salle) Row(verticalAlignment = Alignment.CenterVertically) {
                Text("Voyageurs", style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
                bj.vivi.app.ui.composants.Compteur(voyageurs, { voyageurs = it }, 1, logement.capacite.takeIf { it > 0 } ?: 20, "voyageurs", compact = true)
            }
            Separateur()
            ChampTexte(nom, { nom = it.take(120) }, "Nom du client", erreur = e["nom"])
            ChampTexte(telephone, { telephone = it.take(20) }, "Téléphone (facultatif)", clavier = KeyboardType.Phone, indication = "01 97 00 00 00", erreur = e["telephone"])
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                ChampTexte(montant, { montant = it.filter(Char::isDigit).take(8) }, "Prix convenu", Modifier.weight(1f), clavier = KeyboardType.Number, erreur = e["montant"])
                ChampTexte(encaisse, { encaisse = it.filter(Char::isDigit).take(8) }, "Déjà encaissé", Modifier.weight(1f), clavier = KeyboardType.Number, erreur = e["encaisse"])
            }
            ChampTexte(note, { note = it.take(255) }, "Note interne (facultatif)", indication = "Visible par vous et vos gestionnaires")
            (e["reservation"] ?: e["formule"] ?: e["heure"] ?: e["creneau_id"])?.let { Bandeau(it, ton = TonPuce.Danger, icone = Icons.Outlined.Info) }
            BoutonPrincipal(
                "Ajouter au calendrier", {
                    onValider(logement.id, mapOf(
                        "formule" to formule, "date" to date.toString(), "voyageurs" to voyageurs.takeIf { !salle }, "nom" to nom.trim(),
                        "heure" to heure.takeIf { formule == "heure" }, "duree" to duree.takeIf { formule == "heure" },
                        "creneau_id" to creneau.takeIf { formule == "demi_journee" }, "jours" to jours.takeIf { formule == "journee" },
                        "telephone" to telephone.trim().ifEmpty { null }, "montant" to montant.toIntOrNull(), "encaisse" to encaisse.toIntOrNull(),
                        "note" to note.trim().ifEmpty { null },
                    ))
                },
                Modifier.fillMaxWidth(), actif = nom.trim().length >= 2, enCours = enCours, icone = Icons.Outlined.Add,
            )
        }
    }
    if (choixDate) ChoixDate(date, onFermer = { choixDate = false }) { date = it; choixDate = false }
}

/* ---------- Détail d'une réservation (vue propriétaire / gestionnaire) ---------- */

class ReservationProViewModel(etatNav: SavedStateHandle) : EcranViewModel<Reservation>() {
    val reference: String = checkNotNull(etatNav["ref"])
    init { recharger() }
    override suspend fun charger() = appel { reservationPro(reference) }.map { it.data }

    fun encaisser(montant: Int, fait: () -> Unit) = action {
        appel { encaisser(reference, corps("montant" to montant)) }.map { remplacer(it.data); Messages.afficher("Encaissement enregistré."); fait() }
    }

    /** Disponibilités pour déplacer : null pendant le chargement. */
    var deplacement by mutableStateOf<OptionsDeplacement?>(null)
        private set
    var chargementDeplacement by mutableStateOf(false)
        private set
    var erreurDeplacement by mutableStateOf(false)
        private set
    private var tacheDeplacement: Job? = null

    fun chargerDeplacement(date: String? = null) {
        tacheDeplacement?.cancel()
        tacheDeplacement = viewModelScope.launch {
            chargementDeplacement = true
            erreurDeplacement = false
            appel { optionsDeplacement(reference, date) }
                .onSuccess { deplacement = it.data }
                .onFailure { erreurDeplacement = deplacement == null; Messages.erreur(it.message ?: "") }
            chargementDeplacement = false
        }
    }

    fun deplacer(date: LocalDate, heure: String, fait: () -> Unit) = action {
        appel { deplacer(reference, corps("date" to date.toString(), "heure" to heure)) }.map { remplacer(it.data); Messages.afficher(it.message ?: "Réservation déplacée."); fait() }
    }

    fun retirer(fait: () -> Unit) = action {
        appel { retirer(reference) }.map { Messages.afficher(it.message ?: "Réservation retirée."); fait() }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranReservationPro(onRetour: () -> Unit, vm: ReservationProViewModel = viewModel()) {
    val context = LocalContext.current
    var encaisser by remember { mutableStateOf(false) }
    var deplacer by remember { mutableStateOf(false) }
    var retirer by remember { mutableStateOf(false) }
    val r0 = vm.etat.donnees

    Scaffold(
        containerColor = Vivi.Sand,
        topBar = { BarreTitre("Réservation ${vm.reference}", onRetour) },
        bottomBar = {
            // Action principale toujours visible en bas de l'écran
            if (r0 != null) {
                val actif = r0.statut.valeur in setOf("confirmee", "consommee", "en_cours")
                val peutEncaisser = r0.estManuelle && r0.resteAPayer > 0 && actif
                val peutDeplacer = r0.statut.valeur in setOf("confirmee", "consommee")
                if (peutEncaisser || peutDeplacer) bj.vivi.app.ui.composants.BarreAction(Modifier.navigationBarsPadding()) {
                    if (peutEncaisser) {
                        if (peutDeplacer) BoutonContour("Déplacer", { deplacer = true }, icone = Icons.Outlined.EventRepeat)
                        BoutonPrincipal("Encaisser", { encaisser = true }, Modifier.weight(1f), icone = Icons.Outlined.Payments, enCours = vm.enCours)
                    } else {
                        BoutonPrincipal("Déplacer la réservation", { deplacer = true }, Modifier.weight(1f), icone = Icons.Outlined.EventRepeat, enCours = vm.enCours)
                    }
                }
            }
        },
    ) { marges ->
        AvecEtat(vm, Modifier.padding(marges), squelette = { bj.vivi.app.ui.composants.SqueletteDetail(it) }) { r ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                // ---------- Client ----------
                Carte(remplissage = PaddingValues(0.dp)) {
                    Column {
                        Box(Modifier.fillMaxWidth().height(6.dp).background(couleurStatut(r.statut.valeur)))
                        Column(Modifier.padding(16.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                PuceStatut(r.statut)
                                Spacer(Modifier.width(8.dp))
                                if (r.estManuelle) Puce("Hors plateforme", TonPuce.Neutre)
                            }
                            Spacer(Modifier.height(12.dp))
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                bj.vivi.app.ui.composants.Avatar((r.client?.nom ?: "C").split(' ').mapNotNull { it.firstOrNull() }.joinToString(""), taille = 48.dp)
                                Spacer(Modifier.width(12.dp))
                                Column(Modifier.weight(1f)) {
                                    Text(r.client?.nom ?: "Client", style = MaterialTheme.typography.titleLarge)
                                    // Numéro WhatsApp donné à la réservation (tout pays), sinon le téléphone béninois du compte
                                    val contact = r.client?.whatsapp ?: r.client?.telephone
                                    Text(contact?.let { if (r.client?.whatsapp != null) "WhatsApp : $it" else it } ?: "Téléphone non communiqué", style = MaterialTheme.typography.bodySmall)
                                }
                            }
                            val numeroAppel = r.client?.whatsapp ?: r.client?.telephone
                            val lienWa = r.client?.lienWhatsapp ?: r.client?.telephone?.let { "https://wa.me/" + numeroWhatsapp(it) }
                            if (numeroAppel != null || lienWa != null) {
                                Spacer(Modifier.height(12.dp))
                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                    numeroAppel?.let { tel -> BoutonSecondaire("Appeler", { appeler(context, tel) }, Modifier.weight(1f), icone = Icons.Outlined.Call) }
                                    lienWa?.let { lien ->
                                        BoutonContour("WhatsApp", { bj.vivi.app.ui.ouvrirLien(context, lien) }, Modifier.weight(1f), icone = Icons.AutoMirrored.Outlined.Chat)
                                    }
                                }
                            }
                        }
                    }
                }

                // ---------- Séjour : arrivée → départ ----------
                Carte {
                    Column {
                        TitreSection("Séjour", surtitre = r.logement?.titre)
                        Spacer(Modifier.height(12.dp))
                        EtapeSejour(Icons.AutoMirrored.Outlined.Login, if (r.estSalle) "Début" else "Arrivée", Fmt.quand(r.debut), true)
                        EtapeSejour(Icons.AutoMirrored.Outlined.Logout, if (r.estSalle) "Fin" else "Départ", Fmt.quand(r.fin), false)
                        Separateur(Modifier.padding(vertical = 8.dp))
                        LigneMontant("Formule", r.formule.libelle)
                        LigneMontant("Durée", r.duree)
                        r.personnes?.let { LigneMontant(if (r.estSalle) "Personnes" else "Voyageurs", it) }
                        r.scanneLe?.let { LigneMontant("Arrivé à", Fmt.heure(it), couleur = Vivi.Success) }
                        if (r.services.isNotEmpty()) {
                            LigneMontant("Services", r.services.joinToString(", ") { s -> s.nom + if (s.quantite > 1) " × ${s.quantite}" else "" })
                        }
                        r.noteInterne?.let { Spacer(Modifier.height(6.dp)); Bandeau(it, ton = TonPuce.Neutre, icone = Icons.Outlined.Info) }
                    }
                }

                // ---------- Montants ----------
                Carte {
                    Column {
                        TitreSection("Montants")
                        Spacer(Modifier.height(6.dp))
                        LigneMontant("Location", Fmt.fcfa(r.montants.location))
                        if (r.montants.services > 0) LigneMontant("Services", Fmt.fcfa(r.montants.services))
                        if (r.estManuelle) {
                            LigneMontant("Encaissé sur place", Fmt.fcfa(r.montantEncaisse))
                            Separateur(Modifier.padding(vertical = 6.dp))
                            LigneMontant("Reste à payer", Fmt.fcfa(r.resteAPayer), fort = true, couleur = if (r.resteAPayer > 0) Vivi.OrangeInk else Vivi.Success)
                        } else {
                            Separateur(Modifier.padding(vertical = 6.dp))
                            // Déjà retirées du crédit : rémunération de VIVI (gestion confiée) et part marketing
                            if (r.partGestion > 0) LigneMontant("Gestion VIVI", "− " + Fmt.fcfa(r.partGestion), aide = "Rémunération prévue au contrat de gestion")
                            if (r.partMarketing > 0) LigneMontant("Part marketing", "− " + Fmt.fcfa(r.partMarketing),
                                aide = r.agent?.let { "Versée à ${it.nom}" } ?: "Sans agent : elle revient à VIVI")
                            LigneMontant("Crédité à votre solde", Fmt.fcfa(r.creditProprietaire), fort = true, aide = "Au démarrage du séjour (scan du QR Code)")
                        }
                    }
                }

                if (!r.estManuelle && r.statut.valeur == "confirmee") {
                    Bandeau("Payée en ligne : vous ne pouvez la déplacer que si le client est absent ou à moins de 3 h de l’arrivée.", ton = TonPuce.Teal, icone = Icons.Outlined.Info)
                }
                if (r.estManuelle && r.statut.valeur == "confirmee") {
                    BoutonContour("Retirer du calendrier", { retirer = true }, Modifier.fillMaxWidth(), danger = true, icone = Icons.Outlined.EventBusy, enCours = vm.enCours)
                }
                Spacer(Modifier.height(8.dp))
            }
        }
    }

    val r = vm.etat.donnees
    if (encaisser && r != null) {
        var montant by remember { mutableStateOf(r.resteAPayer.toString()) }
        ModalBottomSheet(onDismissRequest = { encaisser = false }, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Vivi.White) {
            Column(Modifier.padding(horizontal = 20.dp).padding(bottom = 24.dp).navigationBarsPadding().imePadding(), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Text("Encaisser un paiement", style = MaterialTheme.typography.headlineSmall)
                ChampTexte(montant, { montant = it.filter(Char::isDigit).take(8) }, "Montant reçu (FCFA)", clavier = KeyboardType.Number, aide = "Reste à payer : ${Fmt.fcfa(r.resteAPayer)}")
                BoutonPrincipal("Enregistrer l’encaissement", { vm.encaisser(montant.toIntOrNull() ?: 0) { encaisser = false } }, Modifier.fillMaxWidth(),
                    actif = (montant.toIntOrNull() ?: 0) > 0, enCours = vm.enCours, icone = Icons.Outlined.Payments)
            }
        }
    }
    if (deplacer && r != null) FeuilleDeplacer(vm) { deplacer = false }
    if (retirer) {
        AlertDialog(
            onDismissRequest = { retirer = false },
            title = { Text("Retirer cette réservation ?") },
            text = { Text("La période redevient réservable en ligne. Cette action ne peut pas être annulée.") },
            confirmButton = { TextButton(onClick = { retirer = false; vm.retirer(onRetour) }) { Text("Retirer", color = Vivi.Danger, fontWeight = FontWeight.SemiBold) } },
            dismissButton = { TextButton(onClick = { retirer = false }) { Text("Garder", color = Vivi.Teal700) } },
            containerColor = Vivi.White,
        )
    }
}

/** Numéro béninois pour wa.me : « 01 97 00 00 00 » → « 2290197000000 ». */
private fun numeroWhatsapp(tel: String): String {
    val chiffres = tel.filter(Char::isDigit)
    return if (chiffres.startsWith("229")) chiffres else "229$chiffres"
}

@Composable
private fun EtapeSejour(icone: androidx.compose.ui.graphics.vector.ImageVector, libelle: String, valeur: String, avecTrait: Boolean) {
    Row {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Box(Modifier.size(32.dp).clip(CircleShape).background(Vivi.Teal50), contentAlignment = Alignment.Center) {
                Icon(icone, null, tint = Vivi.Teal700, modifier = Modifier.size(18.dp))
            }
            if (avecTrait) Box(Modifier.width(2.dp).height(18.dp).background(Vivi.Teal100))
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.padding(top = 4.dp)) {
            Text(libelle, style = MaterialTheme.typography.bodySmall)
            Text(valeur.ifEmpty { "—" }, style = MaterialTheme.typography.titleSmall, color = Vivi.Ink)
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun FeuilleDeplacer(vm: ReservationProViewModel, onFermer: () -> Unit) {
    // Jours complets et heures prises calculés par le serveur, comme pour le report du client
    LaunchedEffect(Unit) { vm.chargerDeplacement() }
    val options = vm.deplacement
    val date = options?.date?.let { Fmt.jour(it) }
    var heure by remember(options?.date) { mutableStateOf<String?>(null) }
    var choixDate by remember { mutableStateOf(false) }
    ModalBottomSheet(onDismissRequest = onFermer, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Vivi.White) {
        Column(Modifier.padding(horizontal = 20.dp).padding(bottom = 24.dp).navigationBarsPadding().verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(14.dp)) {
            Text("Déplacer la réservation", style = MaterialTheme.typography.headlineSmall)
            Text("Même durée et même formule ; le client est prévenu. Les jours complets sont grisés, les heures prises barrées.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)
            when {
                options == null && vm.erreurDeplacement -> BoutonContour("Réessayer", { vm.chargerDeplacement() }, Modifier.fillMaxWidth())
                options == null -> Box(Modifier.fillMaxWidth().padding(24.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Vivi.Teal700) }
                date == null -> Text("Aucun jour libre d’ici le ${Fmt.jourLong(options.dateMax)}.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Ink)
                else -> {
                    BoutonContour(Fmt.jourLong(date), { choixDate = true }, Modifier.fillMaxWidth(), icone = Icons.Outlined.CalendarMonth)
                    Text("Heure d’arrivée", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
                    if (vm.chargementDeplacement) {
                        Box(Modifier.fillMaxWidth().padding(12.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Vivi.Teal700) }
                    } else if (options.heures.isEmpty()) {
                        Text("Aucune heure libre ce jour-là.", style = MaterialTheme.typography.bodyMedium)
                    } else {
                        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            options.heures.forEach { h -> CaseChoix(h.heure, choisi = h.heure == heure, actif = h.libre) { heure = h.heure } }
                        }
                    }
                    val choisie = heure
                    BoutonPrincipal(
                        if (choisie == null) "Choisissez une heure" else "Déplacer au ${Fmt.jourCourt(date)} à $choisie",
                        { if (choisie != null) vm.deplacer(date, choisie, onFermer) },
                        Modifier.fillMaxWidth(), actif = choisie != null && !vm.chargementDeplacement, enCours = vm.enCours, icone = Icons.Outlined.EventRepeat,
                    )
                }
            }
        }
    }
    if (choixDate && options != null && date != null) {
        val complets = remember(options.joursComplets) { options.joursComplets.mapNotNull { Fmt.jour(it) }.toSet() }
        ChoixDate(date, min = Fmt.jour(options.dateMin) ?: Fmt.aujourdhui(), max = Fmt.jour(options.dateMax), exclues = complets, onFermer = { choixDate = false }) {
            vm.chargerDeplacement(it.toString()); choixDate = false
        }
    }
}

/* ======================================================================
 * Calendrier d'un logement : état de chaque jour, détail, blocage (pro-calendrier.html)
 * ====================================================================== */

class CalendrierViewModel : EcranViewModel<Calendrier>() {
    var logements by mutableStateOf<List<LogementGere>>(emptyList())
        private set
    var logement by mutableStateOf<Long?>(null)
        private set
    var mois by mutableStateOf(YearMonth.now(Fmt.fuseau))
        private set
    var jour by mutableStateOf(Fmt.aujourdhui())
        private set

    init {
        viewModelScope.launch {
            appel { logementsGeres() }
                .onSuccess { logements = it.data; logement = it.data.firstOrNull()?.id; if (logement != null) recharger() else remplacerVide() }
                .onFailure { recharger() }
        }
    }

    private fun remplacerVide() {
        etat = bj.vivi.app.ui.Etat.Erreur(bj.vivi.app.data.ErreurVivi(404, "Aucun logement à afficher. Ajoutez un logement depuis le site VIVI, ou demandez au propriétaire de vous en confier un."))
    }

    override suspend fun charger(): Result<Calendrier> {
        val id = logement ?: return Result.failure(bj.vivi.app.data.ErreurVivi(404, "Aucun logement à afficher."))
        return appel { calendrier(id, mois.toString(), jour.toString()) }.map { it.data }
    }

    fun choisirLogement(id: Long) { logement = id; recharger() }
    /** Mois précédent ou suivant ; le jour choisi devient aujourd'hui s'il est dans ce mois, sinon le 1er. */
    fun moisSuivant(pas: Long) {
        mois = mois.plusMonths(pas)
        val aujourdhui = Fmt.aujourdhui()
        jour = if (YearMonth.from(aujourdhui) == mois) aujourdhui else mois.atDay(1)
        recharger()
    }
    fun choisirJour(d: LocalDate) { jour = d; recharger(silencieux = true) }

    fun bloquer(motif: String) = action {
        appel { bloquer(logement!!, corps("jour" to jour.toString(), "motif" to motif.trim().ifEmpty { null })) }.map { Messages.afficher(it.message ?: "Journée bloquée."); recharger(silencieux = true) }
    }

    fun debloquer(blocage: Long) = action {
        appel { debloquer(logement!!, blocage) }.map { Messages.afficher(it.message ?: "Journée débloquée."); recharger(silencieux = true) }
    }

    var erreursAjout by mutableStateOf<Map<String, String>>(emptyMap())
        private set

    fun oublierErreursAjout() { erreursAjout = emptyMap() }

    /** Client venu hors plateforme, ajouté depuis le calendrier : le jour se met à jour aussitôt. */
    fun ajouter(logementId: Long, donnees: Map<String, Any?>, fait: (String) -> Unit) {
        erreursAjout = emptyMap()
        action(surErreur = { e -> erreursAjout = e.champs.ifEmpty { mapOf("reservation" to e.message) } }) {
            appel { ajouterReservation(logementId, corps(*donnees.toList().toTypedArray())) }.map { r ->
                Messages.afficher(r.message ?: "Réservation ajoutée.")
                // Le calendrier montre le logement et le jour de la réservation ajoutée
                logement = logementId
                donnees["date"]?.toString()?.let { d -> runCatching { LocalDate.parse(d) }.getOrNull() }?.let { d -> jour = d; mois = YearMonth.from(d) }
                recharger(silencieux = true)
                fait(r.data.reference)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranCalendrier(onReservation: (String) -> Unit, vm: CalendrierViewModel = viewModel()) {
    var ajout by remember { mutableStateOf(false) }
    val ouvrirAjout = { vm.oublierErreursAjout(); ajout = true }
    val logementChoisi = vm.logements.firstOrNull { it.id == vm.logement }
    Scaffold(containerColor = Vivi.Sand, floatingActionButton = {
        if (vm.logements.isNotEmpty()) androidx.compose.material3.ExtendedFloatingActionButton(
            onClick = ouvrirAjout, containerColor = Vivi.Orange400, contentColor = Vivi.Ink,
            icon = { Icon(Icons.Outlined.Add, null) },
            text = { Text("Réserver", fontWeight = FontWeight.SemiBold, color = Vivi.Ink) },
        )
    }, topBar = {
        Column(Modifier.background(Vivi.White)) {
            BarreTitre("Calendrier")
            if (vm.logements.size > 1) {
                LazyRow(Modifier.padding(bottom = 8.dp), contentPadding = PaddingValues(horizontal = 16.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(vm.logements) { l -> PuceFiltre(l.titre, vm.logement == l.id) { vm.choisirLogement(l.id) } }
                }
            }
        }
    }) { marges ->
        AvecEtat(vm, Modifier.padding(marges), squelette = { bj.vivi.app.ui.composants.SqueletteDetail(it) }) { cal ->
            ContenuCalendrier(vm, cal, logementChoisi?.estSalle == true, onReservation, ouvrirAjout)
        }
    }
    if (ajout && vm.logements.isNotEmpty()) {
        // Jour choisi dans le calendrier (aujourd'hui s'il est passé) et logement affiché, déjà sélectionnés
        val jour = if (vm.jour.isBefore(Fmt.aujourdhui())) Fmt.aujourdhui() else vm.jour
        FeuilleAjoutReservation(vm.logements, vm.logement, jour, vm.erreursAjout, vm.enCours, onFermer = { ajout = false }) { id, donnees ->
            vm.ajouter(id, donnees) { ajout = false }
        }
    }
}

@Composable
private fun ContenuCalendrier(vm: CalendrierViewModel, cal: Calendrier, salle: Boolean, onReservation: (String) -> Unit, onAjouter: () -> Unit) {
    var motif by remember { mutableStateOf("") }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 96.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        Carte {
            Column {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    IconButton(onClick = { vm.moisSuivant(-1) }) { Icon(Icons.AutoMirrored.Outlined.KeyboardArrowLeft, contentDescription = "Mois précédent", tint = Vivi.Ink) }
                    Text(Fmt.mois(vm.mois.atDay(1)), style = MaterialTheme.typography.titleLarge, textAlign = TextAlign.Center, modifier = Modifier.weight(1f))
                    IconButton(onClick = { vm.moisSuivant(1) }) { Icon(Icons.AutoMirrored.Outlined.KeyboardArrowRight, contentDescription = "Mois suivant", tint = Vivi.Ink) }
                }
                Row(Modifier.fillMaxWidth()) {
                    listOf("L", "M", "M", "J", "V", "S", "D").forEach { Text(it, Modifier.weight(1f), textAlign = TextAlign.Center, fontSize = 12.sp, color = Vivi.Muted, fontWeight = FontWeight.SemiBold) }
                }
                Spacer(Modifier.height(4.dp))
                val premier = LocalDate.parse(cal.jours.firstOrNull()?.date ?: vm.mois.atDay(1).toString())
                val decalage = premier.dayOfWeek.value - DayOfWeek.MONDAY.value
                val cases = List(decalage) { null } + cal.jours
                cases.chunked(7).forEach { semaine ->
                    Row(Modifier.fillMaxWidth()) {
                        semaine.forEach { j -> Box(Modifier.weight(1f).aspectRatio(1f).padding(2.dp)) { if (j != null) CaseJour(j, LocalDate.parse(j.date) == vm.jour) { vm.choisirJour(LocalDate.parse(j.date)) } } }
                        repeat(7 - semaine.size) { Spacer(Modifier.weight(1f)) }
                    }
                }
                Spacer(Modifier.height(10.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Legende(Vivi.White, "Libre"); Legende(Vivi.Teal100, "Partiel"); Legende(Vivi.Teal700, "Complet"); Legende(Vivi.Segmented, "Bloqué")
                }
            }
        }

        // ---------- Détail du jour ----------
        Carte {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                TitreSection(Fmt.jourLong(vm.jour))
                val blocage = cal.jour.blocage
                if (blocage != null) {
                    Bandeau("Journée bloquée${blocage.motif?.let { " : $it" } ?: ""}. Elle n’est pas proposée aux clients.", ton = TonPuce.Neutre, icone = Icons.Outlined.Block)
                    BoutonContour("Débloquer la journée", { vm.debloquer(blocage.id) }, Modifier.fillMaxWidth(), icone = Icons.Outlined.LockOpen, enCours = vm.enCours)
                } else if (cal.jour.reservations.isEmpty()) {
                    Text(if (salle) "Aucun événement ce jour-là." else "Aucun séjour ce jour-là.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)
                }
                cal.jour.reservations.forEach { r -> Separateur(); LigneSejour(r) { onReservation(r.reference) } }
                // Client venu directement : réservation hors plateforme sur ce jour (logement ou salle)
                if (blocage == null && !vm.jour.isBefore(Fmt.aujourdhui())) {
                    Spacer(Modifier.height(4.dp))
                    BoutonPrincipal(
                        if (salle) "Réserver la salle ce jour" else "Ajouter une réservation ce jour",
                        onAjouter, Modifier.fillMaxWidth(), icone = Icons.Outlined.Add,
                    )
                }
                if (blocage == null && cal.jour.reservations.isEmpty() && !vm.jour.isBefore(Fmt.aujourdhui())) {
                    Spacer(Modifier.height(4.dp))
                    ChampTexte(motif, { motif = it.take(120) }, "Motif (facultatif)", indication = "Travaux, usage personnel…")
                    BoutonContour("Bloquer cette journée", { vm.bloquer(motif); motif = "" }, Modifier.fillMaxWidth(), icone = Icons.Outlined.Block, enCours = vm.enCours)
                }
            }
        }
    }
}

@Composable
private fun CaseJour(j: Calendrier.JourCal, choisi: Boolean, onClick: () -> Unit) {
    val d = LocalDate.parse(j.date)
    val (fond, texte) = when (j.etat) {
        "complet" -> Vivi.Teal700 to Vivi.White
        "partiel" -> Vivi.Teal100 to Vivi.Teal800
        "bloque" -> Vivi.Segmented to Vivi.Muted
        else -> Vivi.White to Vivi.Ink
    }
    val libelle = mapOf("libre" to "libre", "partiel" to "partiellement réservé", "complet" to "complet", "bloque" to "bloqué")[j.etat] ?: j.etat
    Box(
        Modifier.fillMaxSize().clip(RoundedCornerShape(10.dp)).background(fond)
            .border(if (choisi) 2.dp else 1.dp, if (choisi) Vivi.Orange500 else Vivi.Line, RoundedCornerShape(10.dp))
            .clickable(role = Role.Button, onClick = onClick)
            .semantics { contentDescription = "${Fmt.jourLong(d)}, $libelle" },
        contentAlignment = Alignment.Center,
    ) {
        Text(
            "${d.dayOfMonth}", color = if (j.passe) texte.copy(alpha = .45f) else texte, fontWeight = if (d == Fmt.aujourdhui()) FontWeight.ExtraBold else FontWeight.SemiBold,
            textDecoration = if (j.etat == "bloque") androidx.compose.ui.text.style.TextDecoration.LineThrough else null,
        )
    }
}

@Composable
private fun Legende(couleur: Color, texte: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(12.dp).clip(CircleShape).background(couleur).border(1.dp, Vivi.LineStrong, CircleShape))
        Spacer(Modifier.width(4.dp))
        Text(texte, fontSize = 12.sp, color = Vivi.Muted)
    }
}
