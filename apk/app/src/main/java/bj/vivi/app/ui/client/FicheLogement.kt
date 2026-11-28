package bj.vivi.app.ui.client

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.outlined.Celebration
import androidx.compose.material.icons.outlined.DirectionsCar
import androidx.compose.material.icons.outlined.FreeBreakfast
import androidx.compose.material.icons.outlined.LocalBar
import androidx.compose.material.icons.outlined.LocalLaundryService
import androidx.compose.material.icons.outlined.NearMe
import androidx.compose.material.icons.outlined.Restaurant
import androidx.compose.material.icons.outlined.RoomService
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.material.icons.outlined.WbSunny
import androidx.compose.material.icons.outlined.WbTwilight
import androidx.compose.material3.Surface
import androidx.compose.runtime.derivedStateOf
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalDensity
import bj.vivi.app.ui.donnees
import bj.vivi.app.ui.composants.SqueletteFiche
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.selection.selectable
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.text.style.TextDecoration
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
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Bathtub
import androidx.compose.material.icons.outlined.Bed
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.CleaningServices
import androidx.compose.material.icons.outlined.Group
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.MeetingRoom
import androidx.compose.material.icons.outlined.Place
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.rounded.Star
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.IconButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.Avis
import bj.vivi.app.data.Disponibilites
import bj.vivi.app.data.ErreurVivi
import bj.vivi.app.data.FicheLogement
import bj.vivi.app.data.Logement
import bj.vivi.app.data.Pub
import bj.vivi.app.data.Reseau
import bj.vivi.app.ui.composants.EspacePub
import bj.vivi.app.ui.composants.rememberSuiviPub
import bj.vivi.app.data.appel
import bj.vivi.app.data.corps
import bj.vivi.app.ui.AvecEtat
import bj.vivi.app.ui.EcranViewModel
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.composants.Bandeau
import bj.vivi.app.ui.composants.BarreAction
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.Chargement
import bj.vivi.app.ui.composants.Compteur
import bj.vivi.app.ui.composants.LigneMontant
import bj.vivi.app.ui.composants.Note
import bj.vivi.app.ui.composants.PhotoLogement
import bj.vivi.app.ui.composants.Prix
import bj.vivi.app.ui.composants.Puce
import bj.vivi.app.ui.composants.Segmente
import bj.vivi.app.ui.composants.Separateur
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.pluriel
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.time.LocalDate

class FicheLogementViewModel(etatNav: SavedStateHandle) : EcranViewModel<FicheLogement>() {
    val slug: String = checkNotNull(etatNav["slug"])
    var avis by mutableStateOf<List<Avis>>(emptyList())
        private set

    /** Autres logements libres aujourd'hui, du plus proche au plus loin de celui-ci. */
    var proches by mutableStateOf<List<Logement>>(emptyList())
        private set

    init {
        recharger()
        viewModelScope.launch { appel { avis(slug) }.onSuccess { avis = it.data.take(4) } }
    }

    override suspend fun charger() = appel { logement(slug) }.onSuccess { chargerProches(it.data) }

    private fun chargerProches(l: Logement) {
        val z = l.zone
        viewModelScope.launch {
            appel {
                rechercher(
                    q = null, ville = if (z == null) l.ville.ifBlank { null } else null, date = Fmt.aujourdhui().toString(), voyageurs = 1,
                    formule = null, categorie = l.categorie, budget = null, tri = if (z != null) "distance" else "note", types = emptyList(), equipements = emptyList(),
                    lat = z?.latitude, lng = z?.longitude, page = 1,
                )
            }.onSuccess { r -> proches = r.data.filter { it.id != l.id }.take(8) }
        }
    }
}

@Composable
fun EcranFicheLogement(
    onRetour: () -> Unit,
    onConnexion: () -> Unit,
    onTunnel: (String) -> Unit,
    onLogement: (String) -> Unit = {},
    vm: FicheLogementViewModel = viewModel(),
) {
    var reserver by remember { mutableStateOf(false) }
    val fiche = vm.etat.donnees

    Scaffold(
        containerColor = Vivi.White,
        bottomBar = {
            fiche?.data?.let { l ->
                BarreAction(Modifier.navigationBarsPadding()) {
                    l.aPartirDe?.let { Prix(it.prixTexte, it.unite, Modifier.weight(1f)) } ?: Spacer(Modifier.weight(1f))
                    BoutonPrincipal(if (fiche.apercu) "Aperçu" else "Réserver", { reserver = true }, actif = !fiche.apercu && l.tarifs.isNotEmpty())
                }
            }
        },
    ) { marges ->
        AvecEtat(
            vm, Modifier.padding(bottom = marges.calculateBottomPadding()), actualisable = false,
            squelette = { m ->
                // Le bouton retour reste utilisable pendant le chargement
                Box(m) {
                    SqueletteFiche()
                    IconButton(
                        onClick = onRetour, modifier = Modifier.statusBarsPadding().padding(8.dp),
                        colors = IconButtonDefaults.iconButtonColors(containerColor = Vivi.White.copy(alpha = .94f)),
                    ) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Retour", tint = Vivi.Ink) }
                }
            },
        ) { f ->
            Contenu(f, vm.avis, vm.proches, onRetour, onLogement)
        }
    }

    if (reserver && fiche != null) {
        FeuilleReserver(fiche.data, onFermer = { reserver = false }, onConnexion = { reserver = false; onConnexion() }, onTunnel = { reserver = false; onTunnel(it) })
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun Contenu(f: FicheLogement, avis: List<Avis>, proches: List<Logement>, onRetour: () -> Unit, onLogement: (String) -> Unit) {
    val l = f.data
    val pubs = rememberSuiviPub()
    val defilement = rememberScrollState()
    // Barre compacte (retour + titre) dès que la photo est passée : le texte ne glisse plus sous la barre d'état
    val seuil = with(LocalDensity.current) { (LocalConfiguration.current.screenWidthDp.dp * .75f - 72.dp).toPx() }
    val compacte by remember { derivedStateOf { defilement.value > seuil } }
    // Visionneuse plein écran : index de la photo ouverte, null si fermée
    val photos = l.photos.ifEmpty { listOf(bj.vivi.app.data.Photo(url = l.photo)) }
    var photoOuverte by remember { mutableStateOf<Int?>(null) }

    Box(Modifier.fillMaxSize()) {
    Column(Modifier.fillMaxSize().verticalScroll(defilement)) {
        // ---------- Photos ----------
        Box {
            val pages = rememberPagerState { photos.size }
            HorizontalPager(pages, Modifier.fillMaxWidth().aspectRatio(4f / 3f)) { i ->
                PhotoLogement(
                    photos[i].url, photos[i].legende ?: "Photo ${i + 1} de ${l.titre}",
                    Modifier.fillMaxSize().clickable(enabled = photos[i].url != null, onClickLabel = "Agrandir la photo") { photoOuverte = i },
                )
            }
            // Dégradé discret pour garder lisibles l'heure et les icônes de la barre d'état
            Box(Modifier.fillMaxWidth().height(96.dp).background(Brush.verticalGradient(listOf(Color.Black.copy(alpha = .35f), Color.Transparent))))
            IconButton(
                onClick = onRetour,
                modifier = Modifier.statusBarsPadding().padding(8.dp),
                colors = IconButtonDefaults.iconButtonColors(containerColor = Vivi.White.copy(alpha = .94f)),
            ) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Retour", tint = Vivi.Ink) }
            if (photos.size > 1) {
                Row(Modifier.align(Alignment.BottomCenter).padding(bottom = 12.dp), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    repeat(photos.size) { i ->
                        Box(Modifier.size(if (i == pages.currentPage) 8.dp else 6.dp).clip(CircleShape)
                            .background(if (i == pages.currentPage) Vivi.White else Vivi.White.copy(alpha = .55f)))
                    }
                }
                Puce("${pages.currentPage + 1} / ${photos.size}", TonPuce.Sombre, modifier = Modifier.align(Alignment.BottomEnd).padding(12.dp))
            }
        }

        Column(Modifier.padding(horizontal = 16.dp, vertical = 16.dp)) {
            if (f.apercu) {
                Bandeau("Aperçu : ${if (l.estSalle) "cette salle" else "ce logement"} n’est pas encore visible des clients.", ton = TonPuce.Orange, icone = Icons.Outlined.Info)
                Spacer(Modifier.height(12.dp))
            }
            Row(verticalAlignment = Alignment.CenterVertically) {
                l.type?.let { Puce(it.libelle, TonPuce.Teal) }
                Spacer(Modifier.weight(1f))
                Note(l.note, l.nombreAvis)
            }
            Spacer(Modifier.height(10.dp))
            Text(l.titre, style = MaterialTheme.typography.headlineMedium)
            Spacer(Modifier.height(4.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Outlined.Place, null, tint = Vivi.Muted, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(4.dp))
                Text(listOfNotNull(l.quartier, l.ville).joinToString(", "), style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)
            }
            Spacer(Modifier.height(16.dp))

            // Capacité (.amenity-inline)
            FlowRow(horizontalArrangement = Arrangement.spacedBy(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Caracteristique(Icons.Outlined.Group, if (l.estSalle) l.capaciteLibelle ?: pluriel(l.capacite, "place") else pluriel(l.capacite, "voyageur"))
                if (!l.estSalle && l.chambres > 0) Caracteristique(Icons.Outlined.MeetingRoom, pluriel(l.chambres, "chambre"))
                if (!l.estSalle && l.lits > 0) Caracteristique(Icons.Outlined.Bed, pluriel(l.lits, "lit"))
                if (!l.estSalle && l.sallesDeBain > 0) Caracteristique(Icons.Outlined.Bathtub, pluriel(l.sallesDeBain, "salle") + " de bain")
            }

            Spacer(Modifier.height(20.dp))
            Separateur()
            Spacer(Modifier.height(20.dp))

            // ---------- Tarifs ----------
            TitreSection("Formules et tarifs")
            Spacer(Modifier.height(10.dp))
            Carte(fond = Vivi.Teal50, remplissage = PaddingValues(horizontal = 16.dp, vertical = 4.dp)) {
                Column {
                    l.tarifs.forEachIndexed { i, t ->
                        if (i > 0) Separateur()
                        Row(Modifier.fillMaxWidth().padding(vertical = 12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Box(Modifier.size(36.dp).clip(CircleShape).background(Vivi.White), contentAlignment = Alignment.Center) {
                                Icon(iconeFormule(t.formule.valeur), null, tint = Vivi.Teal700, modifier = Modifier.size(20.dp))
                            }
                            Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) {
                                Text(t.formule.libelle, style = MaterialTheme.typography.titleSmall, color = Vivi.Teal800)
                                if (l.ouvert24h && t.formule.valeur == "heure") Text("Ouvert 24 h/24", fontSize = 12.sp, color = Vivi.Teal800)
                            }
                            Text(t.prixTexte, style = MaterialTheme.typography.titleMedium, maxLines = 1)
                            Text(" " + t.unite, fontSize = 12.sp, color = Vivi.Muted, maxLines = 1)
                        }
                    }
                }
            }
            l.tempsPreparation?.takeIf { it > 0 }?.let {
                Spacer(Modifier.height(8.dp))
                Text(if (l.estSalle) "La salle est préparée pendant $it min après chaque événement." else "Le logement est préparé pendant $it min après chaque départ.", style = MaterialTheme.typography.bodySmall)
            }

            l.description?.takeIf { it.isNotBlank() }?.let {
                Spacer(Modifier.height(24.dp))
                TitreSection(if (l.estSalle) "La salle" else "Le logement")
                Spacer(Modifier.height(8.dp))
                Text(it, style = MaterialTheme.typography.bodyLarge)
            }

            if (l.equipements.isNotEmpty()) {
                Spacer(Modifier.height(24.dp))
                TitreSection("Équipements")
                Spacer(Modifier.height(10.dp))
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    l.equipements.forEach { Puce(it.nom, TonPuce.Ligne, Icons.Outlined.CheckCircle) }
                }
            }

            if (l.services.isNotEmpty()) {
                Spacer(Modifier.height(24.dp))
                TitreSection("Services à la carte", surtitre = "En option")
                Spacer(Modifier.height(10.dp))
                l.services.forEach { s ->
                    Row(Modifier.fillMaxWidth().padding(vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                        Box(Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).background(Vivi.Orange50), contentAlignment = Alignment.Center) {
                            Icon(iconeService(s.nom), null, tint = Vivi.OrangeInk, modifier = Modifier.size(20.dp))
                        }
                        Spacer(Modifier.width(12.dp))
                        Column(Modifier.weight(1f)) {
                            Text(s.nom, style = MaterialTheme.typography.titleSmall)
                            s.description?.let { Text(it, style = MaterialTheme.typography.bodySmall, maxLines = 2, overflow = TextOverflow.Ellipsis) }
                        }
                        Text(Fmt.fcfa(s.prix), fontWeight = FontWeight.SemiBold, color = Vivi.Ink)
                    }
                }
            }

            Spacer(Modifier.height(24.dp))
            Bandeau(
                "L’adresse exacte et l’itinéraire vous sont donnés sur votre billet, juste après le paiement.",
                ton = TonPuce.Teal, icone = Icons.Outlined.Lock,
            )

            if (avis.isNotEmpty()) {
                Spacer(Modifier.height(24.dp))
                TitreSection(if (l.estSalle) "Avis des clients" else "Avis des voyageurs")
                Spacer(Modifier.height(10.dp))
                LazyRow(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    items(avis) { a ->
                        Carte(Modifier.width(260.dp)) {
                            Column {
                                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.semantics(mergeDescendants = true) { contentDescription = "${a.note} étoiles sur 5" }) {
                                    repeat(5) { i -> Icon(Icons.Rounded.Star, null, tint = if (i < a.note) Vivi.Orange500 else Vivi.Line, modifier = Modifier.size(16.dp)) }
                                }
                                Spacer(Modifier.height(6.dp))
                                Text(a.commentaire ?: "", style = MaterialTheme.typography.bodyMedium, maxLines = 4, overflow = TextOverflow.Ellipsis)
                                Spacer(Modifier.height(8.dp))
                                Text("${a.auteur ?: "Client"} · ${Fmt.numerique(a.le)}", style = MaterialTheme.typography.bodySmall)
                            }
                        }
                    }
                }
            }
            Spacer(Modifier.height(24.dp))
        }

        // ---------- Logements à proximité ----------
        if (proches.isNotEmpty()) {
            TitreSection(
                (if (l.estSalle) "salles" else "logements").let { mot ->
                    if (l.zone != null) "${mot.replaceFirstChar(Char::uppercase)} à proximité" else "Autres $mot à ${l.ville}"
                },
                Modifier.padding(horizontal = 16.dp), surtitre = "Libres aujourd’hui",
            )
            Spacer(Modifier.height(10.dp))
            LazyRow(contentPadding = PaddingValues(horizontal = 16.dp), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                items(proches, key = { it.id }) { p -> CarteProche(p) { onLogement(p.slug) } }
            }
            Spacer(Modifier.height(24.dp))
        }

        // ---------- Publicité en bas de fiche (jamais sur l'aperçu d'un propriétaire) ----------
        if (!f.apercu) EspacePub(Pub.FICHE, pubs, Modifier.padding(start = 16.dp, end = 16.dp, bottom = 24.dp))
    }

    // ---------- Barre compacte ----------
    AnimatedVisibility(compacte, enter = fadeIn(), exit = fadeOut()) {
        Surface(color = Vivi.White, shadowElevation = 4.dp, modifier = Modifier.fillMaxWidth()) {
            Row(Modifier.statusBarsPadding().padding(horizontal = 4.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = onRetour) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Retour", tint = Vivi.Ink) }
                Text(l.titre, style = MaterialTheme.typography.titleMedium, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f))
                Spacer(Modifier.width(12.dp))
            }
        }
    }
    }

    // ---------- Visionneuse plein écran ----------
    photoOuverte?.let { i ->
        bj.vivi.app.ui.composants.Visionneuse(photos.filter { it.url != null }, i, l.titre) { photoOuverte = null }
    }
}

/** Petite carte d'un logement voisin : photo, distance, quartier, titre, prix et note. */
@Composable
internal fun CarteProche(l: Logement, onClick: () -> Unit) {
    Carte(Modifier.width(232.dp), onClick = onClick, remplissage = PaddingValues(0.dp)) {
        Column {
            Box {
                PhotoLogement(l.photo, null, Modifier.fillMaxWidth().aspectRatio(4f / 3f))
                l.distanceKm?.let { d ->
                    Puce(if (d < 1) "à ${(d * 1000).toInt()} m" else "à ${"%.1f".format(Fmt.locale, d)} km", TonPuce.Ligne,
                        Icons.Outlined.NearMe, Modifier.padding(8.dp))
                }
            }
            Column(Modifier.padding(12.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(listOfNotNull(l.quartier, l.ville).joinToString(", "), style = MaterialTheme.typography.bodySmall,
                        maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f))
                    Note(l.note, l.nombreAvis)
                }
                Spacer(Modifier.height(2.dp))
                Text(l.titre, style = MaterialTheme.typography.titleSmall, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Spacer(Modifier.height(6.dp))
                l.aPartirDe?.let { Prix(it.prixTexte, it.unite) }
            }
        }
    }
}

private fun iconeFormule(valeur: String): ImageVector = when (valeur) {
    "heure" -> Icons.Outlined.Schedule
    "demi_journee" -> Icons.Outlined.WbTwilight
    else -> Icons.Outlined.WbSunny
}

/** Icône d'un service à la carte d'après son nom (le serveur n'en envoie pas). */
private fun iconeService(nom: String): ImageVector {
    val n = nom.lowercase(Fmt.locale)
    return when {
        "boisson" in n || "bar" in n -> Icons.Outlined.LocalBar
        "ménage" in n || "menage" in n || "nettoyage" in n -> Icons.Outlined.CleaningServices
        "déco" in n || "deco" in n || "romant" in n || "anniversaire" in n -> Icons.Outlined.Celebration
        "déjeuner" in n || "dejeuner" in n || "breakfast" in n -> Icons.Outlined.FreeBreakfast
        "repas" in n || "dîner" in n || "diner" in n || "cuisin" in n -> Icons.Outlined.Restaurant
        "chauffeur" in n || "transfert" in n || "navette" in n || "taxi" in n -> Icons.Outlined.DirectionsCar
        "linge" in n || "lessive" in n || "pressing" in n -> Icons.Outlined.LocalLaundryService
        else -> Icons.Outlined.RoomService
    }
}

@Composable
private fun Caracteristique(icone: ImageVector, texte: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(icone, null, tint = Vivi.Teal700, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(6.dp))
        Text(texte, style = MaterialTheme.typography.bodyMedium, color = Vivi.Ink)
    }
}

/* ======================================================================
 * Bloc « Réserver » : chaque choix est renvoyé au serveur, qui corrige
 * les incohérences et recalcule le prix (GET /logements/{slug}/disponibilites).
 * ====================================================================== */

class ReserverViewModel : ViewModel() {
    var etat by mutableStateOf<Disponibilites?>(null)
        private set
    var calcul by mutableStateOf(false)
        private set
    var erreur by mutableStateOf<String?>(null)
        private set
    var envoi by mutableStateOf(false)
        private set
    /** Jour proposé à la place d’aujourd’hui, où il ne reste plus rien de libre (null : aucun report). */
    var jourPropose by mutableStateOf<String?>(null)
        private set
    private var slug = ""
    /** Salle : réservée entière, aucun nombre de personnes n'est envoyé. */
    private var salle = false
    private var tache: Job? = null

    fun demarrer(l: Logement) {
        if (slug == l.slug) return
        slug = l.slug
        salle = l.estSalle
        demander(emptyMap())
    }

    /** Envoie l'état actuel modifié par [changement] ; les autres choix sont conservés. */
    fun changer(changement: Map<String, String?>) {
        val e = etat ?: return
        val base = mapOf(
            "date" to e.date, "formule" to e.formule, "voyageurs" to e.voyageurs.toString().takeIf { !salle },
            "heure" to e.heure, "duree" to e.duree?.toString(), "creneau_id" to e.creneauId?.toString(), "jours" to e.jours?.toString(),
        )
        // Date avancée par l’application : une autre formule est peut-être encore libre aujourd’hui
        val retour = if ("formule" in changement && e.date == jourPropose) mapOf("date" to e.dateMin) else emptyMap()
        demander(base + retour + changement)
    }

    private fun demander(params: Map<String, String?>) {
        tache?.cancel()
        // Bouton « Continuer » bloqué dès le choix, pas seulement après le délai : l’ancien prix ne vaut plus
        calcul = true
        tache = viewModelScope.launch {
            delay(150)   // regroupe les appuis rapides sur + / −
            lire(params)
                .onSuccess { recu ->
                    val libre = if (recu.date == recu.dateMin && recu.complet) prochainJourLibre(recu, params) else null
                    if (libre != null) jourPropose = libre.date
                    else if (recu.date != jourPropose) jourPropose = null
                    etat = libre ?: recu
                    erreur = null
                }
                .onFailure { erreur = it.message }
            calcul = false
        }
    }

    private suspend fun lire(params: Map<String, String?>) =
        appel { disponibilites(slug, params.filterValues { it != null }.mapValues { it.value!! }) }.map { it.data }

    /** Plus rien de libre aujourd’hui : premier des jours suivants où la formule est libre (null : aucun, ou réseau coupé). */
    private suspend fun prochainJourLibre(recu: Disponibilites, params: Map<String, String?>): Disponibilites? {
        val aujourdhui = Fmt.jour(recu.dateMin) ?: return null
        for (n in 1L..JOURS_CHERCHES) {
            val jour = lire(params + mapOf("date" to aujourdhui.plusDays(n).toString(), "formule" to recu.formule)).getOrNull() ?: return null
            if (!jour.complet) return jour
        }
        return null
    }

    private companion object {
        const val JOURS_CHERCHES = 7L
    }

    fun continuer(onConnexion: () -> Unit, onTunnel: (String) -> Unit) {
        val e = etat ?: return
        // Réponse du serveur pas encore reçue, ou réservation déjà envoyée : on ne valide pas
        if (calcul || envoi || e.devis == null) return
        if (Reseau.session.jeton == null) {
            onConnexion()
            return
        }
        viewModelScope.launch {
            envoi = true
            appel {
                reserver(slug, corps(
                    "date" to e.date, "formule" to e.formule, "voyageurs" to e.voyageurs.takeIf { !salle }, "heure" to e.heure,
                    "duree" to e.duree, "creneau_id" to e.creneauId, "jours" to e.jours,
                    "agent" to bj.vivi.app.ui.CodesAgent.pour(slug),
                ))
            }.onSuccess { onTunnel(it.data.reference) }
                .onFailure {
                    erreur = it.message
                    changer(emptyMap())   // créneau pris entre-temps : disponibilités relues
                }
            envoi = false
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun FeuilleReserver(l: Logement, onFermer: () -> Unit, onConnexion: () -> Unit, onTunnel: (String) -> Unit, vm: ReserverViewModel = viewModel()) {
    LaunchedEffect(l.slug) { vm.demarrer(l) }
    var choixDate by remember { mutableStateOf(false) }
    val etat = vm.etat

    ModalBottomSheet(onDismissRequest = onFermer, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Vivi.White) {
        if (etat == null) {
            Box(Modifier.height(320.dp)) { Chargement() }
            return@ModalBottomSheet
        }
        Column(Modifier.navigationBarsPadding().verticalScroll(rememberScrollState()).padding(horizontal = 20.dp)) {
            Text("Réserver", style = MaterialTheme.typography.headlineSmall)
            Text(l.titre, style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Spacer(Modifier.height(16.dp))

            if (etat.formules.size > 1) {
                Segmente(etat.formules.map { it.valeur to it.court }, etat.formule, { vm.changer(mapOf("formule" to it)) })
                Spacer(Modifier.height(16.dp))
            }

            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f).clip(RoundedCornerShape(12.dp)).clickable(role = Role.Button) { choixDate = true }.padding(vertical = 6.dp)) {
                    Text(if (l.estSalle) "Date de l’événement" else "Date d’arrivée", fontSize = 12.sp, color = Vivi.Muted)
                    Text(Fmt.jourLong(etat.date), style = MaterialTheme.typography.titleMedium, color = Vivi.Teal700)
                }
                // Nombre de voyageurs : seulement pour un logement (une salle se loue entière)
                if (!l.estSalle) {
                    Column(horizontalAlignment = Alignment.End) {
                        Text("Voyageurs", fontSize = 12.sp, color = Vivi.Muted)
                        Compteur(etat.voyageurs, { vm.changer(mapOf("voyageurs" to it.toString())) }, 1, etat.capacite, "voyageurs")
                    }
                }
            }
            Spacer(Modifier.height(16.dp))

            // Plus rien de libre aujourd’hui : la date a été avancée au premier jour libre
            if (etat.date == vm.jourPropose) {
                val jour = Fmt.jour(etat.date)
                val demain = jour != null && jour == Fmt.jour(etat.dateMin)?.plusDays(1)
                val quand = if (demain) "demain, ${Fmt.jourLong(etat.date).lowercase(Fmt.locale)}" else Fmt.jourLong(etat.date).lowercase(Fmt.locale)
                Bandeau(
                    when (etat.formule) {
                        "heure" -> "Plus d’heure libre aujourd’hui. Voici les heures disponibles $quand."
                        "demi_journee" -> "Plus de créneau libre aujourd’hui. Voici les créneaux disponibles $quand."
                        else -> "Plus de journée libre aujourd’hui. Prochaine date disponible : $quand."
                    },
                    icone = Icons.Outlined.Info,
                )
                Spacer(Modifier.height(16.dp))
            }

            when (etat.formule) {
                "heure" -> {
                    Text(if (l.estSalle) "Heure de début" else "Heure d’arrivée", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
                    Spacer(Modifier.height(8.dp))
                    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        etat.heures.forEach { h ->
                            CaseChoix(h.heure, choisi = h.heure == etat.heure, actif = h.libre) { vm.changer(mapOf("heure" to h.heure)) }
                        }
                    }
                    if (etat.heure != null && etat.duree != null) {
                        Spacer(Modifier.height(16.dp))
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text("Durée", style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
                            Compteur(etat.duree, { vm.changer(mapOf("duree" to it.toString())) }, etat.dureeMin ?: 1, etat.dureeMax ?: 24, "durée", suffixe = " h")
                        }
                    }
                }
                "demi_journee" -> {
                    Text("Créneau", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
                    Spacer(Modifier.height(8.dp))
                    etat.creneaux.forEach { c ->
                        CaseChoix("${c.nom} · ${c.de} – ${c.a}", choisi = c.id == etat.creneauId, actif = c.libre, Modifier.fillMaxWidth().padding(bottom = 8.dp)) {
                            vm.changer(mapOf("creneau_id" to c.id.toString()))
                        }
                    }
                }
                "journee" -> if (etat.jours != null) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("Nombre de jours", style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
                        Compteur(etat.jours, { vm.changer(mapOf("jours" to it.toString())) }, 1, etat.joursMax ?: 1, "jours")
                    }
                }
            }
            etat.aide?.let {
                Spacer(Modifier.height(8.dp))
                // Texte du serveur, déjà adapté à une salle (fin le jour même, « cette salle »)
                val texte = it
                Text(texte, style = MaterialTheme.typography.bodySmall)
            }
            (vm.erreur ?: etat.message)?.let {
                Spacer(Modifier.height(12.dp))
                Bandeau(it, ton = TonPuce.Orange, icone = Icons.Outlined.Info)
            }

            etat.devis?.let { d ->
                Spacer(Modifier.height(16.dp))
                Separateur()
                Spacer(Modifier.height(8.dp))
                LigneMontant("Location", Fmt.fcfa(d.location))
                LigneMontant("Commission VIVI (${Fmt.taux(d.tauxCommission)} %)", Fmt.fcfa(d.commission), aide = "Non remboursable en cas d’annulation")
                Separateur(Modifier.padding(vertical = 6.dp))
                LigneMontant("Total", Fmt.fcfa(d.total), fort = true)
            }
            Spacer(Modifier.height(16.dp))
            BoutonPrincipal(
                if (Reseau.session.jeton == null) "Me connecter pour réserver" else "Continuer",
                { vm.continuer(onConnexion, onTunnel) },
                Modifier.fillMaxWidth().padding(bottom = 16.dp),
                actif = etat.devis != null && !vm.calcul && !vm.envoi,
                enCours = vm.envoi,
            )
        }
    }

    if (choixDate) {
        val complets = remember(etat?.joursComplets) { etat?.joursComplets.orEmpty().mapNotNull { Fmt.jour(it) }.toSet() }
        ChoixDate(LocalDate.parse(etat?.date ?: Fmt.aujourdhui().toString()), exclues = complets, onFermer = { choixDate = false }) {
            vm.changer(mapOf("date" to it.toString()))
            choixDate = false
        }
    }
}

/** Case de choix (heure, créneau) : turquoise si choisie, barrée si indisponible. */
@Composable
fun CaseChoix(texte: String, choisi: Boolean, actif: Boolean = true, modifier: Modifier = Modifier, onClick: () -> Unit) {
    val forme = RoundedCornerShape(10.dp)
    Box(
        modifier
            .heightIn(min = 48.dp)
            .clip(forme)
            .background(when { choisi -> Vivi.Teal700; actif -> Vivi.White; else -> Vivi.Segmented })
            .border(1.dp, if (choisi) Vivi.Teal700 else Vivi.LineStrong, forme)
            .selectable(selected = choisi, enabled = actif, role = Role.RadioButton, onClick = onClick)
            .semantics { if (!actif) stateDescription = "indisponible" }
            .padding(horizontal = 14.dp, vertical = 12.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            texte, fontWeight = FontWeight.SemiBold,
            color = when { choisi -> Vivi.White; actif -> Vivi.Ink; else -> Vivi.Muted },
            textDecoration = if (!actif) TextDecoration.LineThrough else null,
        )
    }
}
