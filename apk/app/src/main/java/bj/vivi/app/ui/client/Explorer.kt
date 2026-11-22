package bj.vivi.app.ui.client

import android.Manifest
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.material.icons.outlined.MyLocation
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.platform.LocalContext
import bj.vivi.app.data.Position
import androidx.compose.material.icons.outlined.EventRepeat
import androidx.compose.material.icons.outlined.PhoneAndroid
import androidx.compose.material.icons.outlined.QrCode2
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.material.icons.outlined.WbSunny
import androidx.compose.material.icons.outlined.WbTwilight
import androidx.compose.runtime.collectAsState
import bj.vivi.app.ui.composants.SqueletteCarteLogement
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import bj.vivi.app.ui.composants.Bandeau
import androidx.compose.foundation.background
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
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.selection.selectable
import androidx.compose.material.icons.outlined.Apartment
import androidx.compose.material.icons.outlined.Celebration
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.lazy.rememberLazyListState
import bj.vivi.app.data.Pub
import bj.vivi.app.ui.composants.EspacePub
import bj.vivi.app.ui.composants.rememberSuiviPub
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.Group
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.Place
import androidx.compose.material.icons.outlined.SearchOff
import androidx.compose.material.icons.outlined.Tune
import androidx.compose.material.icons.outlined.Bed
import androidx.compose.material3.BadgedBox
import androidx.compose.material3.Badge
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.SelectableDates
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.Catalogue
import bj.vivi.app.data.Configuration
import bj.vivi.app.data.ErreurVivi
import bj.vivi.app.data.Logement
import bj.vivi.app.data.Reseau
import bj.vivi.app.data.appel
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.composants.BoutonContour
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.Chargement
import bj.vivi.app.ui.composants.Compteur
import bj.vivi.app.ui.composants.EtatErreur
import bj.vivi.app.ui.composants.EtatVide
import bj.vivi.app.ui.composants.Marque
import bj.vivi.app.ui.composants.Note
import bj.vivi.app.ui.composants.PhotoLogement
import bj.vivi.app.ui.composants.Prix
import bj.vivi.app.ui.composants.Puce
import bj.vivi.app.ui.composants.Segmente
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.pluriel
import bj.vivi.app.ui.theme.Outfit
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.Job
import kotlinx.coroutines.launch
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneOffset

/** Critères de la recherche (App\Services\Recherche::criteres). */
data class Criteres(
    /** « logement » ou « salle » (salles de fêtes et de conférence). */
    val categorie: String = "logement",
    val ville: String? = null,
    val date: LocalDate = Fmt.aujourdhui(),
    /** Voyageurs pour un logement ; places au moins pour une salle (null : toutes capacités). */
    val voyageurs: Int? = if (categorie == "salle") null else 2,
    val formule: String? = null,
    val tri: String = "pertinence",
    val types: Set<String> = emptySet(),
    val equipements: Set<Long> = emptySet(),
) {
    val salle get() = categorie == "salle"
    val nombreFiltres get() = types.size + equipements.size + (if (tri != "pertinence") 1 else 0)

    /** Changer de famille d'espace : types de l'autre famille retirés. */
    fun versCategorie(c: String) = if (c == categorie) this
        else copy(categorie = c, voyageurs = if (c == "salle") null else 2, types = emptySet())

    companion object {
        /** Paliers de capacité proposés pour une salle (documentation de l'API). */
        val PaliersCapacite = listOf(20, 50, 100, 200, 300, 500)
    }
}

/** « 2 voyageurs », « 100 places et plus » ou « Toutes ». */
fun libelleNombre(n: Int?, salle: Boolean) = when {
    !salle -> pluriel(n ?: 1, "voyageur")
    n == null -> "Toutes"
    else -> "$n places et plus"
}

class ExplorerViewModel(categorie: String = "logement") : ViewModel() {
    var config by mutableStateOf<Configuration?>(null)
        private set
    var criteres by mutableStateOf(Criteres(categorie))
        private set
    var resultats by mutableStateOf<List<Logement>>(emptyList())
        private set
    var total by mutableStateOf(0)
        private set
    var chargement by mutableStateOf(true)
        private set
    var suiteEnCours by mutableStateOf(false)
        private set
    var erreur by mutableStateOf<ErreurVivi?>(null)
        private set
    /** Position du téléphone : sans ville choisie, les logements les plus proches viennent en premier. */
    var position by mutableStateOf<Position.Point?>(null)
        private set
    /** Aucun logement dans le rayon (25 km) : le serveur renvoie tous les logements, du plus proche au plus loin. */
    var horsRayon by mutableStateOf(false)
        private set
    private var demarre = false
    private var page = 1
    private var pageMax = 1
    private var tache: Job? = null

    init {
        viewModelScope.launch { config = Catalogue.obtenir() }
    }

    /** Première recherche, une fois la position connue (ou refusée). */
    fun demarrer(point: Position.Point?) {
        if (demarre) {
            if (point != null && position == null) { position = point; chercher() }
            return
        }
        demarre = true
        position = point
        chercher()
    }

    fun modifier(nouveaux: Criteres) {
        if (nouveaux == criteres) return
        criteres = nouveaux
        chercher()
    }

    fun chercher() {
        tache?.cancel()
        tache = viewModelScope.launch {
            chargement = true
            erreur = null
            page = 1
            charger(1).onSuccess { (liste, t) -> resultats = liste; total = t }.onFailure { erreur = it as ErreurVivi }
            chargement = false
        }
    }

    fun suite() {
        if (suiteEnCours || chargement || page >= pageMax) return
        viewModelScope.launch {
            suiteEnCours = true
            charger(page + 1).onSuccess { (liste, _) -> page++; resultats = resultats + liste }
            suiteEnCours = false
        }
    }

    private suspend fun charger(p: Int): Result<Pair<List<Logement>, Int>> {
        val c = criteres
        val pos = position
        return appel {
            rechercher(
                ville = c.ville, date = c.date.toString(), voyageurs = c.voyageurs, formule = c.formule, categorie = c.categorie, budget = null,
                tri = if (pos != null && c.tri == "pertinence") "distance" else c.tri,
                types = c.types.toList(), equipements = c.equipements.toList(), lat = pos?.lat, lng = pos?.lng, page = p,
            )
        }.map { r ->
            if (p == 1) horsRayon = r.criteres?.horsRayon == true
            pageMax = r.meta?.pageMax ?: 1
            r.data to (r.meta?.total ?: r.data.size)
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranExplorer(
    onLogement: (String) -> Unit,
    onNotifications: () -> Unit,
    nonLues: Int,
    onCompte: () -> Unit = {},
    categorie: String = "logement",
    /** Espace sans onglets « Logements » et « Salles » (propriétaire, gestionnaire) : la bascule est sur l'écran. */
    basculable: Boolean = false,
    vm: ExplorerViewModel = viewModel { ExplorerViewModel(categorie) },
) {
    val c = vm.criteres
    val pubs = rememberSuiviPub()
    LaunchedEffect(c.ville) { Pub.charger(c.ville) }
    val utilisateur by Reseau.session.utilisateur.collectAsState()
    val context = LocalContext.current
    val portee = rememberCoroutineScope()
    val demandeLocalisation = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) {
        portee.launch { vm.demarrer(Position.obtenir(context)) }
    }
    LaunchedEffect(Unit) {
        if (Position.autorisee(context)) vm.demarrer(Position.obtenir(context))
        else demandeLocalisation.launch(arrayOf(Manifest.permission.ACCESS_COARSE_LOCATION))
    }
    // Retour des réglages (localisation activée) : la position est relue
    var localisationCoupee by remember { mutableStateOf(false) }
    LifecycleEventEffect(Lifecycle.Event.ON_RESUME) {
        localisationCoupee = Position.autorisee(context) && !Position.active(context)
        if (vm.position == null && Position.autorisee(context) && !localisationCoupee) portee.launch { vm.demarrer(Position.obtenir(context)) }
    }
    val presDeMoi = vm.position != null && c.ville == null
    var filtres by remember { mutableStateOf(false) }
    var choixDate by remember { mutableStateOf(false) }
    var choixVoyageurs by remember { mutableStateOf(false) }
    val liste = rememberLazyListState()
    val finAtteinte by remember { derivedStateOf { liste.layoutInfo.visibleItemsInfo.lastOrNull()?.index?.let { it >= liste.layoutInfo.totalItemsCount - 3 } ?: false } }
    LaunchedEffect(finAtteinte) { if (finAtteinte) vm.suite() }

    LazyColumn(state = liste, modifier = Modifier.fillMaxSize().background(Vivi.Sand), contentPadding = PaddingValues(bottom = 24.dp)) {
        // ---------- En-tête de marque (.hero) ----------
        item {
            Column(Modifier.fillMaxWidth().background(Vivi.Teal900).statusBarsPadding().padding(horizontal = 16.dp).padding(top = 8.dp, bottom = 20.dp)) {
                EnTeteMarque(onNotifications, nonLues, onCompte)
                Spacer(Modifier.height(14.dp))
                val prenom = utilisateur?.prenom?.takeIf { it.isNotBlank() }
                val salut = bj.vivi.app.ui.composants.salutationDuMoment()
                Text(if (prenom != null) "$salut $prenom," else "$salut, bienvenue sur VIVI", color = Vivi.Teal200, fontSize = 15.sp, fontWeight = FontWeight.Medium)
                Spacer(Modifier.height(4.dp))
                val moment = when (salut) { "Bonsoir" -> "ce soir"; "Bon après-midi" -> "cet après-midi"; else -> "aujourd’hui" }
                Text(
                    if (c.salle) "Quelle salle pour votre événement ?" else "Où voulez-vous séjourner $moment ?",
                    fontFamily = Outfit, fontWeight = FontWeight.Bold, fontSize = 24.sp, lineHeight = 29.sp, color = Vivi.White,
                )
                Spacer(Modifier.height(6.dp))
                Text(
                    if (c.salle) "Salles de fêtes et de conférence à l’heure, à la demi-journée ou à la journée."
                    else "Appartements meublés à l’heure, à la demi-journée ou à la journée.",
                    color = Vivi.White.copy(alpha = .78f), fontSize = 14.sp,
                )
                Spacer(Modifier.height(16.dp))

                // Logements ou salles (salles de fêtes et de conférence), si la plateforme les propose
                if (basculable && vm.config?.categories?.any { it.valeur == "salle" } == true) {
                    OngletsCategorie(c.categorie) { vm.modifier(c.versCategorie(it)) }
                    Spacer(Modifier.height(12.dp))
                }

                // Carte de recherche (.search-card)
                Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(20.dp)).background(Vivi.White).padding(12.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    val villes = vm.config?.villes.orEmpty()
                    Text("Où ?", fontSize = 12.sp, color = Vivi.Muted, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(start = 4.dp))
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        item {
                            PuceChoix(if (vm.position != null) "Près de moi" else "Toutes les villes", c.ville == null,
                                if (vm.position != null) Icons.Outlined.MyLocation else Icons.Outlined.Place) { vm.modifier(c.copy(ville = null)) }
                        }
                        items(villes) { v -> PuceChoix(v, c.ville == v) { vm.modifier(c.copy(ville = v)) } }
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        CaseCritere(Icons.Outlined.CalendarMonth, "Date", if (c.date == Fmt.aujourdhui()) "Aujourd’hui" else Fmt.jourCourt(c.date), Modifier.weight(1f)) { choixDate = true }
                        CaseCritere(Icons.Outlined.Group, if (c.salle) "Capacité" else "Voyageurs", libelleNombre(c.voyageurs, c.salle), Modifier.weight(1f)) { choixVoyageurs = true }
                    }
                    // Formules en puces défilantes : « Demi-journée » n'est plus coupé sur deux lignes
                    val formules = vm.config?.formules.orEmpty()
                    Text("Combien de temps ?", fontSize = 12.sp, color = Vivi.Muted, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(start = 4.dp))
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        item { PuceChoix("Toutes durées", c.formule == null) { vm.modifier(c.copy(formule = null)) } }
                        items(formules) { f -> PuceChoix(f.court.ifBlank { f.libelle }, c.formule == f.valeur, iconeFormuleRecherche(f.valeur)) { vm.modifier(c.copy(formule = f.valeur)) } }
                    }
                }
            }
        }

        // ---------- Atouts VIVI ----------
        item {
            Row(Modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, top = 16.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Atout(Icons.Outlined.PhoneAndroid, "Mobile Money", Modifier.weight(1f))
                Atout(Icons.Outlined.QrCode2, "Billet QR Code", Modifier.weight(1f))
                Atout(Icons.Outlined.EventRepeat, "Report gratuit", Modifier.weight(1f))
            }
        }

        if (localisationCoupee && vm.position == null) item {
            Carte(
                Modifier.padding(start = 16.dp, end = 16.dp, top = 16.dp), fond = Vivi.Teal50,
                onClick = { runCatching { context.startActivity(android.content.Intent(android.provider.Settings.ACTION_LOCATION_SOURCE_SETTINGS)) } },
                remplissage = PaddingValues(14.dp),
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Outlined.MyLocation, null, tint = Vivi.Teal700)
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f)) {
                        Text(if (c.salle) "Voir les salles près de vous" else "Voir les logements près de vous", style = MaterialTheme.typography.titleSmall, color = Vivi.Teal800)
                        Text("Activez la localisation du téléphone.", style = MaterialTheme.typography.bodySmall)
                    }
                    Text("Activer", color = Vivi.Teal700, fontWeight = FontWeight.SemiBold)
                }
            }
        }

        // ---------- Barre des résultats ----------
        item {
            Row(Modifier.fillMaxWidth().padding(start = 16.dp, end = 8.dp, top = 16.dp, bottom = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    if (presDeMoi && !vm.chargement) {
                        Text("PRÈS DE VOUS", fontSize = 12.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp, color = Vivi.Teal700)
                    }
                    Text(
                        if (vm.chargement) "Recherche…" else pluriel(vm.total, if (c.salle) "salle" else "logement") + " libre" + (if (vm.total > 1) "s" else "") + if (c.date == Fmt.aujourdhui()) " aujourd’hui" else " le ${Fmt.jourCourt(c.date)}",
                        style = MaterialTheme.typography.titleMedium,
                    )
                }
                TextButton(onClick = { filtres = true }, modifier = Modifier.heightIn(min = 48.dp)) {
                    Icon(Icons.Outlined.Tune, null, tint = Vivi.Teal700)
                    Spacer(Modifier.width(6.dp))
                    Text(if (c.nombreFiltres > 0) "Filtres (${c.nombreFiltres})" else "Filtres", color = Vivi.Teal700, fontWeight = FontWeight.SemiBold)
                }
            }
        }

        when {
            vm.chargement -> items(3) { SqueletteCarteLogement(Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) }
            vm.erreur != null -> item { EtatErreur(vm.erreur!!.message, vm.erreur!!.horsLigne, vm::chercher) }
            vm.resultats.isEmpty() -> item {
                EtatVide(Icons.Outlined.SearchOff, if (c.salle) "Aucune salle libre" else "Aucun logement libre", "Essayez une autre date, une autre ville ou moins de filtres.") {
                    BoutonContour("Effacer les critères", { vm.modifier(Criteres(c.categorie)) })
                }
            }
            else -> {
                if (presDeMoi && vm.horsRayon) item {
                    Bandeau(
                        (if (c.salle) "Aucune salle libre" else "Aucun logement libre") + " à moins de 25 km : voici les plus proches de vous.",
                        Modifier.padding(horizontal = 16.dp, vertical = 4.dp), ton = TonPuce.Orange, icone = Icons.Outlined.MyLocation,
                    )
                }
                // Un espace publicitaire tous les « frequence_liste » résultats (rien s'il n'y a pas d'annonce)
                val frequence = Pub.donnees?.frequenceListe?.takeIf { it > 0 } ?: Int.MAX_VALUE
                itemsIndexed(vm.resultats, key = { _, l -> l.id }) { i, l ->
                    Column {
                        CarteLogement(l, Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) { onLogement(l.slug) }
                        if ((i + 1) % frequence == 0) {
                            EspacePub(Pub.RECHERCHE, pubs, Modifier.padding(horizontal = 16.dp, vertical = 8.dp), rang = (i + 1) / frequence - 1)
                        }
                    }
                }
                if (vm.suiteEnCours) item { SqueletteCarteLogement(Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) }
            }
        }
    }

    if (choixDate) {
        ChoixDate(c.date, onFermer = { choixDate = false }) { vm.modifier(c.copy(date = it)); choixDate = false }
    }
    if (choixVoyageurs) {
        ModalBottomSheet(onDismissRequest = { choixVoyageurs = false }, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Vivi.White) {
            var n by remember { mutableStateOf(c.voyageurs) }
            Column(Modifier.padding(horizontal = 20.dp).navigationBarsPadding().padding(bottom = 16.dp)) {
                if (c.salle) {
                    // Salle : capacité par paliers (20 à 500 places)
                    Text("Quelle capacité ?", style = MaterialTheme.typography.titleLarge)
                    Spacer(Modifier.height(6.dp))
                    Text("Nous cherchons les salles qui ont au moins ce nombre de places.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)
                    Spacer(Modifier.height(16.dp))
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        PuceChoix("Toutes", n == null) { n = null }
                        Criteres.PaliersCapacite.forEach { p -> PuceChoix("$p places", n == p) { n = p } }
                    }
                } else {
                    Text("Combien de voyageurs ?", style = MaterialTheme.typography.titleLarge)
                    Spacer(Modifier.height(16.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("Voyageurs", style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
                        Compteur(n ?: 1, { n = it }, 1, 12, "voyageurs")
                    }
                }
                Spacer(Modifier.height(20.dp))
                BoutonPrincipal("Valider", { vm.modifier(c.copy(voyageurs = n)); choixVoyageurs = false }, Modifier.fillMaxWidth())
            }
        }
    }
    if (filtres) {
        FeuilleFiltres(vm.config, c, vm.position != null, onFermer = { filtres = false }) { vm.modifier(it); filtres = false }
    }
}

private fun iconeFormuleRecherche(valeur: String) = when (valeur) {
    "heure" -> Icons.Outlined.Schedule
    "demi_journee" -> Icons.Outlined.WbTwilight
    else -> Icons.Outlined.WbSunny
}

/** Logo à gauche ; à droite, cloche (connecté) et avatar du profil. Sur fond sombre. */
@Composable
internal fun EnTeteMarque(onNotifications: () -> Unit, nonLues: Int, onCompte: () -> Unit) {
    val utilisateur by Reseau.session.utilisateur.collectAsState()
    Row(verticalAlignment = Alignment.CenterVertically) {
        Marque(blanc = true, taille = 30.dp)
        Spacer(Modifier.weight(1f))
        if (Reseau.session.jeton != null) {
            IconButton(onClick = onNotifications) {
                BadgedBox(badge = { if (nonLues > 0) Badge(containerColor = Vivi.Orange400, contentColor = Vivi.Ink) { Text("$nonLues") } }) {
                    Icon(Icons.Outlined.Notifications, contentDescription = "Notifications" + if (nonLues > 0) ", $nonLues non lues" else "", tint = Vivi.White)
                }
            }
        }
        bj.vivi.app.ui.composants.AvatarProfil(utilisateur != null, utilisateur?.nom, onCompte)
    }
}

/** Petit atout de la plateforme sous l'en-tête (icône + libellé). */
@Composable
internal fun Atout(icone: androidx.compose.ui.graphics.vector.ImageVector, texte: String, modifier: Modifier = Modifier) {
    Row(
        modifier.clip(RoundedCornerShape(12.dp)).background(Vivi.White).padding(horizontal = 8.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center,
    ) {
        Icon(icone, null, tint = Vivi.Teal700, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(6.dp))
        Text(texte, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = Vivi.Ink, maxLines = 2, lineHeight = 14.sp)
    }
}

@Composable
private fun PuceChoix(texte: String, choisi: Boolean, icone: androidx.compose.ui.graphics.vector.ImageVector? = null, onClick: () -> Unit) {
    FilterChip(
        selected = choisi, onClick = onClick,
        // Couleur explicite : le style du thème (labelLarge) imposerait sinon le gris foncé, illisible sur le turquoise
        label = { Text(texte, fontWeight = FontWeight.SemiBold, color = if (choisi) Vivi.White else Vivi.Body) },
        leadingIcon = icone?.let { { Icon(it, null, Modifier.size(18.dp)) } },
        shape = RoundedCornerShape(999.dp),
        modifier = Modifier.heightIn(min = 40.dp),
        colors = FilterChipDefaults.filterChipColors(
            selectedContainerColor = Vivi.Teal700, selectedLabelColor = Vivi.White, selectedLeadingIconColor = Vivi.White,
            containerColor = Vivi.White, labelColor = Vivi.Body, iconColor = Vivi.Teal700,
        ),
        border = FilterChipDefaults.filterChipBorder(enabled = true, selected = choisi, borderColor = Vivi.LineStrong, selectedBorderColor = Vivi.Teal700),
    )
}

@Composable
private fun CaseCritere(icone: androidx.compose.ui.graphics.vector.ImageVector, libelle: String, valeur: String, modifier: Modifier, onClick: () -> Unit) {
    Carte(modifier, onClick = onClick, remplissage = PaddingValues(horizontal = 12.dp, vertical = 10.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(icone, null, tint = Vivi.Teal700, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(8.dp))
            Column {
                Text(libelle, fontSize = 12.sp, color = Vivi.Muted)
                Text(valeur, fontWeight = FontWeight.SemiBold, color = Vivi.Ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
        }
    }
}

/** Carte de résultat (.listing-card) : photo 4:3, badges, quartier, titre, capacité, prix et note. */
@Composable
fun CarteLogement(l: Logement, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Carte(modifier.fillMaxWidth(), onClick = onClick, remplissage = PaddingValues(0.dp)) {
        Column {
            Box {
                PhotoLogement(l.photo, null, Modifier.fillMaxWidth().aspectRatio(4f / 3f))
                Row(Modifier.padding(12.dp), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    l.type?.let { Puce(it.libelle.substringBefore(" ("), TonPuce.Ligne) }
                    if (l.ouvert24h) Puce("24 h/24", TonPuce.Orange, Icons.Outlined.Schedule)
                    l.distanceKm?.let { Puce("à ${"%.1f".format(Fmt.locale, it)} km", TonPuce.Ligne) }
                }
            }
            Column(Modifier.padding(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 16.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Outlined.Place, null, tint = Vivi.Muted, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(3.dp))
                    Text(listOfNotNull(l.quartier, l.ville).joinToString(", "), style = MaterialTheme.typography.bodySmall, modifier = Modifier.weight(1f), maxLines = 1)
                    Note(l.note, l.nombreAvis)
                }
                Spacer(Modifier.height(4.dp))
                Text(l.titre, style = MaterialTheme.typography.titleLarge, maxLines = 2, overflow = TextOverflow.Ellipsis)
                Spacer(Modifier.height(2.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(if (l.estSalle) Icons.Outlined.Group else Icons.Outlined.Bed, null, tint = Vivi.Teal700, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(
                        if (l.estSalle) "Jusqu’à " + (l.capaciteLibelle ?: pluriel(l.capacite, "place")) else "Jusqu’à ${pluriel(l.capacite, "voyageur")}" + if (l.chambres > 0) " · ${pluriel(l.chambres, "chambre")}" else "",
                        style = MaterialTheme.typography.bodySmall,
                    )
                }
                Spacer(Modifier.height(8.dp))
                @OptIn(ExperimentalLayoutApi::class)
                FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    l.tarifs.forEach { Puce(it.formule.libelle.removePrefix("À l’").removePrefix("À la "), TonPuce.Teal) }
                }
                Spacer(Modifier.height(10.dp))
                l.aPartirDe?.let { Prix(it.prixTexte, it.unite) }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ChoixDate(initiale: LocalDate, min: LocalDate = Fmt.aujourdhui(), max: LocalDate? = null, onFermer: () -> Unit, onChoix: (LocalDate) -> Unit) {
    val minMs = min.atStartOfDay().toInstant(ZoneOffset.UTC).toEpochMilli()
    val maxMs = max?.atStartOfDay()?.toInstant(ZoneOffset.UTC)?.toEpochMilli()
    val etat = rememberDatePickerState(
        initialSelectedDateMillis = initiale.atStartOfDay().toInstant(ZoneOffset.UTC).toEpochMilli(),
        selectableDates = object : SelectableDates {
            override fun isSelectableDate(utcTimeMillis: Long) = utcTimeMillis >= minMs && (maxMs == null || utcTimeMillis <= maxMs)
        },
    )
    DatePickerDialog(
        onDismissRequest = onFermer,
        confirmButton = {
            TextButton(onClick = { etat.selectedDateMillis?.let { onChoix(Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate()) } ?: onFermer() }) {
                Text("Valider", color = Vivi.Teal700, fontWeight = FontWeight.SemiBold)
            }
        },
        dismissButton = { TextButton(onClick = onFermer) { Text("Annuler", color = Vivi.Muted) } },
    ) { DatePicker(etat, showModeToggle = false) }
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun FeuilleFiltres(config: Configuration?, actuels: Criteres, avecPosition: Boolean, onFermer: () -> Unit, onAppliquer: (Criteres) -> Unit) {
    var c by remember { mutableStateOf(actuels) }
    ModalBottomSheet(onDismissRequest = onFermer, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Vivi.White) {
        Column(Modifier.padding(horizontal = 20.dp).navigationBarsPadding().verticalScroll(rememberScrollState())) {
            Text("Filtres", style = MaterialTheme.typography.headlineSmall)
            Spacer(Modifier.height(16.dp))
            Text("Trier par", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
            Spacer(Modifier.height(8.dp))
            Segmente(listOf("pertinence" to if (avecPosition) "Proximité" else "Pertinence", "prix" to "Prix", "note" to "Note"), c.tri, { c = c.copy(tri = it) })

            // Seulement les types de la famille choisie (logements ou salles) ; ancien serveur sans catégorie : tous
            config?.typesLogement?.filter { (it.categorie ?: "logement") == c.categorie }?.takeIf { it.size > 1 }?.let { types ->
                Spacer(Modifier.height(20.dp))
                Text(if (c.salle) "Type de salle" else "Type de logement", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
                Spacer(Modifier.height(8.dp))
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    types.forEach { t ->
                        val choisi = t.valeur in c.types
                        PuceChoix(t.libelle, choisi) { c = c.copy(types = if (choisi) c.types - t.valeur else c.types + t.valeur) }
                    }
                }
            }
            config?.equipements?.takeIf { it.isNotEmpty() }?.let { eq ->
                Spacer(Modifier.height(20.dp))
                Text("Équipements", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
                Spacer(Modifier.height(8.dp))
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    eq.forEach { e ->
                        val choisi = e.id in c.equipements
                        PuceChoix(e.nom, choisi) { c = c.copy(equipements = if (choisi) c.equipements - e.id else c.equipements + e.id) }
                    }
                }
            }
            Spacer(Modifier.height(24.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.padding(bottom = 16.dp)) {
                BoutonContour("Effacer", { c = c.copy(tri = "pertinence", types = emptySet(), equipements = emptySet()) }, Modifier.weight(1f))
                BoutonPrincipal("Afficher", { onAppliquer(c) }, Modifier.weight(1f))
            }
        }
    }
}


/** Onglets « Logements » / « Salles de fêtes » sur l'en-tête sombre de l'accueil. */
@Composable
private fun OngletsCategorie(choisie: String, onChoix: (String) -> Unit) {
    Row(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(Vivi.White.copy(alpha = .10f)).padding(4.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        listOf(
            Triple("logement", "Logements", Icons.Outlined.Apartment),
            Triple("salle", "Salles", Icons.Outlined.Celebration),
        ).forEach { (valeur, libelle, icone) ->
            val actif = valeur == choisie
            Row(
                Modifier.weight(1f).heightIn(min = 44.dp).clip(RoundedCornerShape(11.dp))
                    .background(if (actif) Vivi.White else androidx.compose.ui.graphics.Color.Transparent)
                    .selectable(selected = actif, role = androidx.compose.ui.semantics.Role.Tab) { onChoix(valeur) }
                    .padding(horizontal = 8.dp),
                horizontalArrangement = Arrangement.Center, verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(icone, null, tint = if (actif) Vivi.Teal700 else Vivi.White, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text(libelle, fontWeight = FontWeight.SemiBold, fontSize = 14.sp, color = if (actif) Vivi.Teal900 else Vivi.White, maxLines = 1)
            }
        }
    }
}
