package bj.vivi.app.ui

import android.net.Uri
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.ConfirmationNumber
import androidx.compose.material.icons.outlined.Dashboard
import androidx.compose.material.icons.automirrored.outlined.EventNote
import androidx.compose.material.icons.outlined.Menu
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.QrCodeScanner
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import kotlinx.coroutines.launch
import androidx.lifecycle.repeatOnLifecycle
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import bj.vivi.app.BuildConfig
import bj.vivi.app.data.Catalogue
import bj.vivi.app.data.Pub
import bj.vivi.app.data.Publicites
import bj.vivi.app.data.RegieAdmob
import bj.vivi.app.data.Reseau
import bj.vivi.app.ui.client.EcranAccueil
import bj.vivi.app.ui.composants.LocalOuvrirPub
import bj.vivi.app.ui.composants.PubOuverture
import androidx.compose.material.icons.outlined.Apartment
import androidx.compose.material.icons.outlined.Celebration
import androidx.compose.material.icons.outlined.Home
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.snapshotFlow
import coil.imageLoader
import kotlinx.coroutines.flow.first
import bj.vivi.app.data.appel
import bj.vivi.app.ui.auth.EcranConnexion
import bj.vivi.app.ui.client.EcranAnnulation
import bj.vivi.app.ui.client.EcranBillet
import bj.vivi.app.ui.client.EcranExplorer
import bj.vivi.app.ui.client.EcranFicheLogement
import bj.vivi.app.ui.client.EcranMesReservations
import bj.vivi.app.ui.client.EcranPaiement
import bj.vivi.app.ui.client.EcranReport
import bj.vivi.app.ui.client.EcranTunnel
import bj.vivi.app.ui.compte.EcranAide
import bj.vivi.app.ui.compte.EcranCompteRetrait
import bj.vivi.app.ui.compte.EcranSuppressionCompte
import bj.vivi.app.ui.compte.EcranCompte
import bj.vivi.app.ui.compte.OngletAide
import bj.vivi.app.ui.compte.EcranNotifications
import bj.vivi.app.ui.compte.EcranParrainage
import androidx.compose.material.icons.outlined.Campaign
import bj.vivi.app.ui.agent.EcranEspaceAgent
import bj.vivi.app.ui.pro.EcranAbonnement
import bj.vivi.app.ui.pro.EcranAgentsPro
import bj.vivi.app.ui.pro.EcranEditionLogement
import bj.vivi.app.ui.pro.EcranCalendrier
import bj.vivi.app.ui.pro.EcranGestionnaires
import bj.vivi.app.ui.pro.EcranLogementsPro
import bj.vivi.app.ui.pro.EcranPlus
import bj.vivi.app.ui.pro.EcranReservationPro
import bj.vivi.app.ui.pro.EcranReservationsPro
import bj.vivi.app.ui.pro.EcranRevenus
import bj.vivi.app.ui.pro.EcranScanner
import bj.vivi.app.ui.pro.EcranTableauDeBord
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/** Routes de l'application. Un client et un pro n'ont pas les mêmes onglets ; le reste est commun. */
object Routes {
    const val ACCUEIL = "accueil"
    const val EXPLORER = "explorer"
    const val SALLES = "salles"
    const val MES_RESERVATIONS = "mes-reservations"
    const val COMPTE = "compte"
    const val PRO_ACCUEIL = "pro/accueil"
    const val PRO_RESERVATIONS = "pro/reservations"
    const val PRO_SCANNER = "pro/scanner"
    const val PRO_CALENDRIER = "pro/calendrier"
    const val PRO_PLUS = "pro/plus"
    const val AGENT = "agent"

    fun logement(slug: String) = "logement/${Uri.encode(slug)}"
    fun tunnel(ref: String) = "tunnel/$ref"
    fun billet(ref: String) = "billet/$ref"
    fun paiement(id: Long) = "paiement/$id"
    fun reservationPro(ref: String) = "pro/reservation/$ref"
}

/** Lien profond reçu par l'activité (vivi://paiement/{id}). */
object LiensEntrants {
    private val _paiement = MutableStateFlow<Long?>(null)
    val paiement: StateFlow<Long?> = _paiement
    private val _logement = MutableStateFlow<String?>(null)
    /** Fiche ouverte par un lien du site (…/logements/{slug}, avec ?agent=AG… pour un lien d'agent marketing). */
    val logement: StateFlow<String?> = _logement

    /** Application ouverte par un lien ou une notification : pas de publicité plein écran. */
    @Volatile var ouvertParLien = false
        private set

    fun recevoir(uri: Uri?) {
        if (uri == null) return
        ouvertParLien = true
        if (uri.scheme == "vivi" && uri.host == "paiement") uri.lastPathSegment?.toLongOrNull()?.let { _paiement.value = it }
        if (uri.scheme in setOf("http", "https")) {
            val segments = uri.pathSegments
            if (segments.size >= 2 && segments[segments.size - 2] == "logements") {
                val slug = segments.last()
                uri.getQueryParameter("agent")?.let { CodesAgent.retenir(slug, it) }
                _logement.value = slug
            }
        }
    }
    /** Appui sur une notification du téléphone (vérification régulière ou push Firebase : mêmes clés). */
    data class Ouverture(val id: String, val ecran: String?, val reference: String?, val cibleId: Long?, val lien: String?)

    private val _notification = MutableStateFlow<Ouverture?>(null)
    val notification: StateFlow<Ouverture?> = _notification

    fun recevoirNotification(intent: android.content.Intent?) {
        val extras = intent?.extras ?: return
        if (extras.containsKey("google.message_id")) ouvertParLien = true   // push affiché par Android, application fermée
        val id = extras.getString(bj.vivi.app.data.Alertes.EXTRA_ID) ?: return
        ouvertParLien = true
        _notification.value = Ouverture(
            id = id,
            ecran = extras.getString(bj.vivi.app.data.Alertes.EXTRA_ECRAN),
            reference = extras.getString(bj.vivi.app.data.Alertes.EXTRA_REFERENCE),
            cibleId = extras.getString(bj.vivi.app.data.Alertes.EXTRA_CIBLE_ID)?.toLongOrNull(),
            lien = extras.getString(bj.vivi.app.data.Alertes.EXTRA_LIEN),
        )
        intent.removeExtra(bj.vivi.app.data.Alertes.EXTRA_ID)   // pas rejoué à la rotation de l'écran
    }
    fun consommerNotification() { _notification.value = null }

    fun consommer() { _paiement.value = null }
    fun consommerLogement() { _logement.value = null }
}

/**
 * Codes d'agent marketing lus dans les liens partagés, par logement, gardés 30 jours comme sur le site.
 * Renvoyés dans POST /logements/{slug}/reserver (champ « agent ») ; le serveur ignore un code invalide.
 */
object CodesAgent {
    private const val DUREE_MS = 30L * 24 * 3600 * 1000
    private var prefs: android.content.SharedPreferences? = null

    fun initialiser(context: android.content.Context) {
        prefs = context.getSharedPreferences("vivi_codes_agent", android.content.Context.MODE_PRIVATE)
    }

    /** Compte supprimé : aucun lien d'agent n'est gardé sur le téléphone. */
    fun oublier() { prefs?.edit()?.clear()?.apply() }

    fun retenir(slug: String, code: String) {
        val propre = code.trim().uppercase()
        if (!Regex("AG[A-Z0-9]{6}").matches(propre)) return
        prefs?.edit()?.putString(slug, "$propre|${System.currentTimeMillis()}")?.apply()
    }

    fun pour(slug: String): String? {
        val (code, le) = prefs?.getString(slug, null)?.split('|')?.takeIf { it.size == 2 } ?: return null
        return code.takeIf { System.currentTimeMillis() - (le.toLongOrNull() ?: 0) < DUREE_MS }
    }
}

private data class Onglet(val route: String, val libelle: String, val icone: ImageVector, val central: Boolean = false)

// Logements et salles : deux entrées séparées, comme sur le site
private val ongletsClient = listOf(
    Onglet(Routes.ACCUEIL, "Accueil", Icons.Outlined.Home),
    Onglet(Routes.EXPLORER, "Logements", Icons.Outlined.Apartment),
    Onglet(Routes.SALLES, "Salles", Icons.Outlined.Celebration),
    Onglet(Routes.MES_RESERVATIONS, "Réservations", Icons.Outlined.ConfirmationNumber),
    Onglet(Routes.COMPTE, "Compte", Icons.Outlined.Person),
)
/** Visiteur : « Mes réservations » n'apparaît qu'une fois connecté. */
private val ongletsVisiteur = ongletsClient.filter { it.route != Routes.MES_RESERVATIONS }
private val ongletsAgent = listOf(
    Onglet(Routes.AGENT, "Mes liens", Icons.Outlined.Campaign),
    Onglet(Routes.EXPLORER, "Logements", Icons.Outlined.Apartment),
    Onglet(Routes.SALLES, "Salles", Icons.Outlined.Celebration),
    Onglet(Routes.MES_RESERVATIONS, "Réservations", Icons.Outlined.ConfirmationNumber),
    Onglet(Routes.COMPTE, "Compte", Icons.Outlined.Person),
)
private val ongletsPro = listOf(
    Onglet(Routes.PRO_ACCUEIL, "Accueil", Icons.Outlined.Dashboard),
    Onglet(Routes.PRO_RESERVATIONS, "Réservations", Icons.AutoMirrored.Outlined.EventNote),
    Onglet(Routes.PRO_SCANNER, "Scanner", Icons.Outlined.QrCodeScanner, central = true),
    Onglet(Routes.PRO_CALENDRIER, "Calendrier", Icons.Outlined.CalendarMonth),
    Onglet(Routes.PRO_PLUS, "Plus", Icons.Outlined.Menu),
)

@Composable
fun AppVivi() {
    val utilisateur by Reseau.session.utilisateur.collectAsState()
    val espacePro = utilisateur?.estPro == true
    val espaceAgent = utilisateur?.estEspaceAgent == true
    val snackbar = remember { SnackbarHostState() }
    var nonLues by remember { mutableIntStateOf(0) }
    var miseAJour by remember { mutableStateOf(false) }
    // Ouverture rapide : la première image ne dessine que l'animation d'ouverture (légère) ;
    // l'application, plus lourde, est composée à l'image suivante, derrière l'animation.
    val cycleVie = androidx.lifecycle.compose.LocalLifecycleOwner.current.lifecycle
    var demarrage by androidx.compose.runtime.saveable.rememberSaveable { mutableStateOf(true) }
    var interfacePrete by remember { mutableStateOf(!demarrage) }
    LaunchedEffect(Unit) { androidx.compose.runtime.withFrameNanos { }; interfacePrete = true }

    // Publicité plein écran : jamais à la première ouverture, ni si l'application est ouverte par un lien ou une notification
    val contextePub = LocalContext.current
    val pleinEcranPossible = remember { Pub.dejaOuverte(contextePub) && demarrage && !LiensEntrants.ouvertParLien }
    var annonceOuverture by remember { mutableStateOf<Publicites.Annonce?>(null) }
    var ouvrirPub by remember { mutableStateOf<(Publicites.Annonce) -> Unit>({}) }
    var surAccueil by remember { mutableStateOf(true) }
    val verrouille by Verrou.verrouille.collectAsState()
    SideEffect { Pub.masquee = demarrage || annonceOuverture != null || (verrouille && utilisateur != null) }

    LaunchedEffect(Unit) {
        val config = Catalogue.obtenir()
        miseAJour = Catalogue.miseAJourRequise(BuildConfig.VERSION_NAME)
        if (config?.publicite != true) return@LaunchedEffect
        Pub.charger(null)
        val p = Pub.donnees ?: return@LaunchedEffect
        val espace = Pub.emplacement(Pub.DEMARRAGE) ?: return@LaunchedEffect
        if (!pleinEcranPossible || !Pub.delaiEcoule(contextePub, p.pleinEcranHeures)) return@LaunchedEffect
        // L'annonce doit être prête au plus tard 3 s après l'animation d'ouverture : ensuite, on ne dérange plus la personne
        val encoreTemps: suspend () -> Boolean = {
            snapshotFlow { demarrage }.first { !it }
            !LiensEntrants.ouvertParLien && surAccueil && !miseAJour && !Verrou.verrouille.value && !Pub.interdite(Reseau.session.utilisateur.value) &&
                cycleVie.currentState.isAtLeast(androidx.lifecycle.Lifecycle.State.RESUMED)
        }
        val limite = launch { snapshotFlow { demarrage }.first { !it }; kotlinx.coroutines.delay(3_000) }
        val preparation = launch {
            val annonce = espace.annonces.randomOrNull()
            val bloc = espace.admob?.bloc
            val activite = contextePub as? android.app.Activity
            if (annonce != null) {
                annonce.image?.let { contextePub.imageLoader.execute(coil.request.ImageRequest.Builder(contextePub).data(it).build()) }
                if (encoreTemps()) {
                    annonceOuverture = annonce
                    Pub.marquerPleinEcran(contextePub)
                }
            } else if (bloc != null && activite != null) {
                snapshotFlow { RegieAdmob.prete }.first { it }
                val ouverture = RegieAdmob.chargerOuverture(contextePub, bloc)
                if (ouverture != null && encoreTemps()) RegieAdmob.montrerOuverture(activite, ouverture) { Pub.marquerPleinEcran(contextePub) }
            }
        }
        limite.join()
        preparation.cancel()
    }
    // Google AdMob : démarré seulement si un espace en a besoin, hors des espaces pro et agent
    LaunchedEffect(Pub.donnees, utilisateur?.id, demarrage) {
        val p = Pub.donnees
        if (p != null && p.avecAdmob && !Pub.interdite(utilisateur)) (contextePub as? android.app.Activity)?.let { RegieAdmob.preparer(it, p.admobTest) }
    }
    LaunchedEffect(Unit) {
        Messages.flux.collect {
            snackbar.currentSnackbarData?.dismiss()   // un nouveau message remplace l'ancien sans attendre
            launch { snackbar.showSnackbar(bj.vivi.app.ui.composants.VisuelsMessage(it)) }
        }
    }
    // Compte relu au démarrage (rôle changé, compte désactivé : 401 → déconnexion)
    val contexte = androidx.compose.ui.platform.LocalContext.current
    val cycle = androidx.lifecycle.compose.LocalLifecycleOwner.current.lifecycle
    LaunchedEffect(utilisateur?.id) {
        if (utilisateur != null) {
            appel { moi() }.onSuccess { Reseau.session.mettreAJour(it.data) }
            // Application ouverte : notifications vérifiées toutes les 10 s (pastille + barre de notifications),
            // et tout de suite au retour au premier plan. GET /notifications/compteur est léger.
            cycle.repeatOnLifecycle(androidx.lifecycle.Lifecycle.State.STARTED) {
                while (true) {
                    bj.vivi.app.data.Alertes.verifier(contexte)?.let { nonLues = it }
                    kotlinx.coroutines.delay(10_000)
                }
            }
        }
    }
    // Android 13+ : autorisation d'afficher les notifications, demandée une fois connecté
    val demandeNotifications = androidx.activity.compose.rememberLauncherForActivityResult(
        androidx.activity.result.contract.ActivityResultContracts.RequestPermission(),
    ) { accordee ->
        if (accordee) kotlinx.coroutines.MainScope().launch { bj.vivi.app.data.Alertes.verifier(contexte)?.let { nonLues = it } }
    }
    LaunchedEffect(utilisateur?.id) {
        if (utilisateur != null && android.os.Build.VERSION.SDK_INT >= 33 && !bj.vivi.app.data.Alertes.autorisees(contexte)) {
            demandeNotifications.launch(android.Manifest.permission.POST_NOTIFICATIONS)
        }
    }

    // Un nouveau graphe de navigation quand on passe de visiteur/client à pro (ou inversement), et à la déconnexion.
    // Un visiteur qui se connecte ou crée son compte client garde ses écrans : il revient sur la fiche qu'il réservait.
    var deconnexions by remember { mutableIntStateOf(0) }
    var etaitConnecte by remember { mutableStateOf(utilisateur != null) }
    if (etaitConnecte != (utilisateur != null)) {
        if (utilisateur == null) deconnexions++
        etaitConnecte = utilisateur != null
    }
    if (interfacePrete) key(espacePro, espaceAgent, deconnexions) {
        val nav = rememberNavController()
        val entree by nav.currentBackStackEntryAsState()
        val route = entree?.destination?.route
        val onglets = when { espacePro -> ongletsPro; espaceAgent -> ongletsAgent; utilisateur == null -> ongletsVisiteur; else -> ongletsClient }
        val depart = when { espacePro -> Routes.PRO_ACCUEIL; espaceAgent -> Routes.AGENT; else -> Routes.ACCUEIL }
        SideEffect {
            surAccueil = route == Routes.ACCUEIL
            ouvrirPub = { a ->
                Pub.clic(a.id)
                val lien = a.lien.orEmpty()
                if (Uri.parse(lien).scheme in setOf("http", "https") && !(a.lienInterne && naviguerDepuisLien(nav, lien))) {
                    if (a.lienInterne) ouvrirPage(contexte, lien) else ouvrirLien(contexte, lien)
                }
            }
        }
        val logementRecu by LiensEntrants.logement.collectAsState()
        LaunchedEffect(logementRecu) {
            logementRecu?.let { slug ->
                LiensEntrants.consommerLogement()
                nav.navigate(Routes.logement(slug)) { launchSingleTop = true }
            }
        }
        // Appui sur une notification du téléphone : marquée lue, puis écran du lien (billet, réservation…)
        val notificationRecue by LiensEntrants.notification.collectAsState()
        LaunchedEffect(notificationRecue, utilisateur?.id) {
            val o = notificationRecue ?: return@LaunchedEffect
            if (utilisateur == null) return@LaunchedEffect
            LiensEntrants.consommerNotification()
            launch { appel { lireNotification(o.id) } }
            ouvrirCible(nav, o.ecran, o.reference, o.cibleId, o.lien, espacePro)
        }
        // Une fois connecté : jeton Firebase envoyé à la plateforme (push instantané, si configuré)
        LaunchedEffect(utilisateur?.id) { if (utilisateur != null) bj.vivi.app.data.Push.enregistrer(contexte) }
        val paiementRecu by LiensEntrants.paiement.collectAsState()
        LaunchedEffect(paiementRecu) {
            paiementRecu?.let { id ->
                LiensEntrants.consommer()
                if (route != "paiement/{id}") nav.navigate(Routes.paiement(id)) { launchSingleTop = true }
            }
        }

        CompositionLocalProvider(LocalOuvrirPub provides { ouvrirPub(it) }) {
            Scaffold(
                contentWindowInsets = WindowInsets(0),
                bottomBar = { if (onglets.any { it.route == route }) BarreOnglets(nav, onglets, route) },
            ) { marges ->
                Box(Modifier.padding(marges).fillMaxSize().background(Vivi.Sand)) {
                    Graphe(nav, depart, nonLues) { nonLues = it }
                }
            }
        }
    }

    annonceOuverture?.let { a ->
        PubOuverture(a, onFermer = { annonceOuverture = null }, onOuvrir = { annonceOuverture = null; ouvrirPub(a) })
    }

    // Messages de succès / d'échec : en haut de l'écran, sous la barre d'état, par-dessus le contenu
    Box(Modifier.fillMaxSize().statusBarsPadding().padding(top = 4.dp), contentAlignment = Alignment.TopCenter) {
        SnackbarHost(snackbar) { bj.vivi.app.ui.composants.BulleMessage(it) }
    }

    // Verrouillage par empreinte (Compte › Sécurité) : par-dessus tout l'écran
    if (verrouille && utilisateur != null) EcranVerrou()

    // Animation d'ouverture : par-dessus tout, une seule fois par lancement (pas à la rotation de l'écran)
    if (demarrage) EcranDemarrage(onFini = { demarrage = false })

    if (miseAJour && !demarrage) {
        val context = LocalContext.current
        AlertDialog(
            onDismissRequest = { miseAJour = false },
            title = { Text("Nouvelle version disponible") },
            text = { Text("Mettez à jour VIVI pour profiter des dernières améliorations et continuer à réserver en toute sécurité.") },
            confirmButton = { TextButton(onClick = { ouvrirLien(context, "market://details?id=${context.packageName}") }) { Text("Mettre à jour", color = Vivi.Teal700, fontWeight = FontWeight.SemiBold) } },
            dismissButton = { TextButton(onClick = { miseAJour = false }) { Text("Plus tard", color = Vivi.Muted) } },
            containerColor = Vivi.White,
        )
    }
}

/** Navigation basse (.vivi-bottomnav / .pro-bottomnav) : turquoise pour l'onglet actif, bouton Scanner orange au centre. */
@Composable
private fun BarreOnglets(nav: NavHostController, onglets: List<Onglet>, route: String?) {
    NavigationBar(containerColor = Vivi.White, tonalElevation = 0.dp) {
        onglets.forEach { o ->
            val actif = route == o.route
            NavigationBarItem(
                selected = actif,
                onClick = {
                    nav.navigate(o.route) {
                        popUpTo(nav.graph.startDestinationId) { saveState = true }
                        launchSingleTop = true
                        restoreState = true
                    }
                },
                icon = {
                    if (o.central) {
                        Box(Modifier.size(48.dp).clip(CircleShape).background(Vivi.Orange400), contentAlignment = Alignment.Center) {
                            Icon(o.icone, null, tint = Vivi.Ink)
                        }
                    } else {
                        Icon(o.icone, null)
                    }
                },
                label = { Text(o.libelle, fontSize = 12.sp, fontWeight = FontWeight.SemiBold) },
                colors = NavigationBarItemDefaults.colors(
                    selectedIconColor = Vivi.Teal700, selectedTextColor = Vivi.Teal700,
                    unselectedIconColor = Vivi.Muted, unselectedTextColor = Vivi.Muted,
                    indicatorColor = if (o.central) Vivi.White else Vivi.Teal50,
                ),
            )
        }
    }
}

@Composable
private fun Graphe(nav: NavHostController, depart: String, nonLues: Int, surNonLues: (Int) -> Unit) {
    val retour: () -> Unit = { if (!nav.popBackStack()) nav.navigate(depart) }
    val connecte = Reseau.session.utilisateur.collectAsState().value != null
    fun versConnexion(mode: String = "connexion") = nav.navigate("connexion?mode=$mode")
    val argRef = listOf(navArgument("ref") { type = NavType.StringType })

    // Transitions : un écran ouvert glisse légèrement de la droite en fondu ; le retour fait l'inverse.
    // Entre onglets (même niveau), simple fondu.
    val ongletsRoutes = (ongletsClient + ongletsPro + ongletsAgent).map { it.route }.toSet()
    fun entreOnglets(a: String?, b: String?) = a in ongletsRoutes && b in ongletsRoutes
    val duree = 280
    NavHost(
        nav, startDestination = depart,
        enterTransition = {
            if (entreOnglets(initialState.destination.route, targetState.destination.route)) fadeIn(tween(220))
            else fadeIn(tween(duree)) + slideInHorizontally(tween(duree, easing = FastOutSlowInEasing)) { it / 6 }
        },
        exitTransition = {
            if (entreOnglets(initialState.destination.route, targetState.destination.route)) fadeOut(tween(160))
            else fadeOut(tween(200)) + slideOutHorizontally(tween(duree)) { -it / 12 }
        },
        popEnterTransition = {
            if (entreOnglets(initialState.destination.route, targetState.destination.route)) fadeIn(tween(220))
            else fadeIn(tween(duree)) + slideInHorizontally(tween(duree, easing = FastOutSlowInEasing)) { -it / 12 }
        },
        popExitTransition = {
            if (entreOnglets(initialState.destination.route, targetState.destination.route)) fadeOut(tween(160))
            else fadeOut(tween(200)) + slideOutHorizontally(tween(duree)) { it / 6 }
        },
    ) {
        /* ---------- Client et visiteur ---------- */
        // Même comportement que la barre du bas quand la route est un onglet ; sinon, écran ouvert par-dessus (pro)
        val versOnglet: (String) -> Unit = { r ->
            if (onglets(depart).any { it == r }) nav.navigate(r) {
                popUpTo(nav.graph.startDestinationId) { saveState = true }; launchSingleTop = true; restoreState = true
            } else nav.navigate(r) { launchSingleTop = true }
        }
        composable(Routes.ACCUEIL) {
            EcranAccueil(
                onLogements = { versOnglet(Routes.EXPLORER) }, onSalles = { versOnglet(Routes.SALLES) },
                onLogement = { nav.navigate(Routes.logement(it)) },
                onNotifications = { nav.navigate("notifications") }, nonLues = nonLues, onCompte = { versOnglet(Routes.COMPTE) },
            )
        }
        composable(Routes.EXPLORER) {
            EcranExplorer(
                onLogement = { nav.navigate(Routes.logement(it)) }, onNotifications = { nav.navigate("notifications") }, nonLues = nonLues,
                onCompte = { versOnglet(Routes.COMPTE) }, basculable = onglets(depart).none { it == Routes.SALLES },
            )
        }
        composable(Routes.SALLES) {
            EcranExplorer(
                onLogement = { nav.navigate(Routes.logement(it)) }, onNotifications = { nav.navigate("notifications") }, nonLues = nonLues,
                onCompte = { versOnglet(Routes.COMPTE) }, categorie = "salle",
            )
        }
        composable(Routes.MES_RESERVATIONS) {
            if (!connecte) EcranCompte(onConnexion = ::versConnexion, onNotifications = {}, onParrainage = {}, onAide = { nav.navigate("aide/$it") })
            else EcranMesReservations(
                onReservation = { r -> nav.navigate(if (r.statut.valeur == "en_attente_paiement") Routes.tunnel(r.reference) else Routes.billet(r.reference)) },
                onExplorer = { versOnglet(Routes.EXPLORER) },
                // Propriétaire, gestionnaire : ses propres séjours de voyageur s'ouvrent depuis « Plus », avec un retour
                onRetour = if (onglets(depart).none { it == Routes.MES_RESERVATIONS }) retour else null,
            )
        }
        composable(Routes.COMPTE) {
            EcranCompte(onConnexion = ::versConnexion, onNotifications = { nav.navigate("notifications") }, onParrainage = { nav.navigate("parrainage") }, onAide = { nav.navigate("aide/$it") }, onCompteRetrait = { nav.navigate("compte-retrait") },
                onReservations = { nav.navigate(Routes.MES_RESERVATIONS) { launchSingleTop = true } },
                onSuppression = { nav.navigate("suppression-compte") { launchSingleTop = true } })
        }
        composable("connexion?mode={mode}", listOf(navArgument("mode") { defaultValue = "connexion" })) { e ->
            // Session ouverte : l'écran se ferme et l'on revient sur la page d'avant (fiche, réservation…)
            EcranConnexion(onFermer = retour, modeInitial = e.arguments?.getString("mode") ?: "connexion")
        }
        composable("logement/{slug}", listOf(navArgument("slug") { type = NavType.StringType })) {
            EcranFicheLogement(
                onRetour = retour, onConnexion = { versConnexion() }, onTunnel = { nav.navigate(Routes.tunnel(it)) },
                onLogement = { nav.navigate(Routes.logement(it)) },
            )
        }
        composable("tunnel/{ref}", argRef) {
            EcranTunnel(
                onRetour = retour,
                onPaiement = { nav.navigate("payer/$it") },
                onBillet = { ref -> nav.navigate(Routes.billet(ref)) { popUpTo("tunnel/{ref}") { inclusive = true } } },
                onAide = { nav.navigate("aide/$it") },
            )
        }
        composable("paiement/{id}", listOf(navArgument("id") { type = NavType.StringType })) {
            EcranPaiement(
                onBillet = { ref ->
                    // Le billet s'ouvre au-dessus de « Mes réservations » : on le retrouve en revenant en arrière
                    nav.navigate(Routes.MES_RESERVATIONS) { popUpTo(depart); launchSingleTop = true }
                    nav.navigate(Routes.billet(ref)) { launchSingleTop = true }
                },
                onAbonnement = { nav.navigate("pro/abonnement") { popUpTo(depart); launchSingleTop = true } },
                onReessayer = { ref -> if (ref != null) nav.navigate(Routes.tunnel(ref)) { popUpTo(depart) } else retour() },
                onFermer = retour,
                onReprendre = { id -> nav.navigate("payer/$id") { popUpTo("paiement/{id}") { inclusive = true } } },
            )
        }
        // Page FedaPay dans l'application ; au retour (vivi://paiement/{id}) ou en quittant : suivi du paiement
        composable("payer/{id}", listOf(navArgument("id") { type = NavType.LongType })) { e ->
            val versSuivi: (Long) -> Unit = { pid -> nav.navigate(Routes.paiement(pid)) { popUpTo("payer/{id}") { inclusive = true } } }
            bj.vivi.app.ui.client.EcranPaiementWeb(e.arguments?.getLong("id") ?: 0L, onTermine = versSuivi, onQuitter = versSuivi)
        }
        composable("billet/{ref}", argRef) {
            EcranBillet(
                onRetour = retour,
                onPayer = { nav.navigate(Routes.tunnel(it)) { popUpTo("billet/{ref}") { inclusive = true } } },
                onReporter = { nav.navigate("reporter/$it") },
                onAnnuler = { nav.navigate("annuler/$it") },
                onLogement = { nav.navigate(Routes.logement(it)) },
            )
        }
        composable("reporter/{ref}", argRef) { e ->
            val ref = e.arguments?.getString("ref").orEmpty()
            EcranReport(onRetour = retour, onFait = { nav.navigate(Routes.billet(ref)) { popUpTo(Routes.billet(ref)) { inclusive = true } } })
        }
        composable("annuler/{ref}", argRef) { e ->
            val ref = e.arguments?.getString("ref").orEmpty()
            EcranAnnulation(onRetour = retour, onFait = { nav.navigate(Routes.billet(ref)) { popUpTo(Routes.billet(ref)) { inclusive = true } } })
        }
        composable("notifications") {
            EcranNotifications(onRetour = { surNonLues(0); retour() }, onOuvrir = { n ->
                val c = n.cible
                if (c?.ecran != null && c.ecran != "notifications") ouvrirCible(nav, c.ecran, c.reference, c.id, n.lien, Reseau.session.utilisateur.value?.estPro == true)
                else n.lien?.let { naviguerDepuisLien(nav, it) }
            })
        }
        composable("parrainage") { EcranParrainage(onRetour = retour) }
        composable("compte-retrait") { EcranCompteRetrait(onRetour = retour) }
        // Compte supprimé : la session fermée recrée le graphe (key) sur l'accueil visiteur
        composable("suppression-compte") { EcranSuppressionCompte(onRetour = retour) }
        composable("aide/{onglet}", listOf(navArgument("onglet") { type = NavType.StringType })) { e ->
            EcranAide(e.arguments?.getString("onglet") ?: OngletAide.ANNULATION, onRetour = retour)
        }

        /* ---------- Propriétaire et gestionnaire ---------- */
        composable(Routes.PRO_ACCUEIL) {
            EcranTableauDeBord(
                onScanner = { nav.navigate(Routes.PRO_SCANNER) { launchSingleTop = true } },
                onReservation = { nav.navigate(Routes.reservationPro(it)) },
                onRevenus = { nav.navigate("pro/revenus") },
                onAbonnement = { nav.navigate("pro/abonnement") },
                onNotifications = { nav.navigate("notifications") },
                nonLues = nonLues,
                onCompte = { nav.navigate(Routes.COMPTE) { launchSingleTop = true } },
            )
        }
        composable(Routes.PRO_RESERVATIONS) { EcranReservationsPro(onReservation = { nav.navigate(Routes.reservationPro(it)) }) }
        composable(Routes.PRO_SCANNER) { EcranScanner(onRetour = null) }
        composable(Routes.PRO_CALENDRIER) { EcranCalendrier(onReservation = { nav.navigate(Routes.reservationPro(it)) }) }
        composable(Routes.PRO_PLUS) {
            EcranPlus(
                onRevenus = { nav.navigate("pro/revenus") },
                onLogements = { nav.navigate("pro/logements") },
                onAbonnement = { nav.navigate("pro/abonnement") },
                onGestionnaires = { nav.navigate("pro/gestionnaires") },
                onAgents = { nav.navigate("pro/agents") },
                onMesSejours = { nav.navigate(Routes.MES_RESERVATIONS) },
                onCompteRetrait = { nav.navigate("compte-retrait") },
                onEspaceAgent = { nav.navigate(Routes.AGENT) },
                onExplorer = { nav.navigate(Routes.EXPLORER) },
                onNotifications = { nav.navigate("notifications") },
                onCompte = { nav.navigate(Routes.COMPTE) },
            )
        }
        composable("pro/reservation/{ref}", argRef) { EcranReservationPro(onRetour = retour) }
        composable("pro/revenus") { EcranRevenus(onRetour = retour, onCompteRetrait = { nav.navigate("compte-retrait") }) }
        composable("pro/logements") {
            EcranLogementsPro(
                onRetour = retour, onApercu = { nav.navigate(Routes.logement(it)) },
                onAjouter = { nav.navigate("pro/logement/nouveau") }, onModifier = { nav.navigate("pro/logement/$it") },
            )
        }
        composable("pro/abonnement") { EcranAbonnement(onRetour = retour, onPaiement = { nav.navigate("payer/$it") }) }
        composable("pro/gestionnaires") { EcranGestionnaires(onRetour = retour) }
        composable("pro/agents") { EcranAgentsPro(onRetour = retour) }
        composable("pro/logement/nouveau") {
            EcranEditionLogement(null, onRetour = retour, onCree = { id ->
                nav.navigate("pro/logement/$id?etape=photos") { popUpTo("pro/logement/nouveau") { inclusive = true } }
            })
        }
        composable(
            "pro/logement/{id}?etape={etape}",
            listOf(navArgument("id") { type = NavType.LongType }, navArgument("etape") { type = NavType.StringType; nullable = true; defaultValue = null }),
        ) { e ->
            EcranEditionLogement(e.arguments?.getLong("id"), onRetour = retour, onApercu = { nav.navigate(Routes.logement(it)) })
        }

        /* ---------- Agent marketing ---------- */
        composable(Routes.AGENT) {
            EcranEspaceAgent(
                onRetour = if (depart == Routes.AGENT) null else retour,
                onLogement = { nav.navigate(Routes.logement(it)) },
                onCompteRetrait = { nav.navigate("compte-retrait") },
            )
        }
    }
}

/** Routes des onglets de l'espace qui démarre sur [depart]. */
private fun onglets(depart: String): List<String> = when (depart) {
    Routes.PRO_ACCUEIL -> ongletsPro
    Routes.AGENT -> ongletsAgent
    else -> ongletsClient
}.map { it.route }

/** Liens des notifications (adresses du site) traduits en écrans de l'application quand c'est possible. */
/**
 * Écran visé par une notification (cible.ecran, voir docs/API-mobile.md § Notifications).
 * Écran inconnu ou référence manquante : le lien du site, sinon la liste des notifications.
 */
private fun ouvrirCible(nav: NavHostController, ecran: String?, reference: String?, id: Long?, lien: String?, espacePro: Boolean) {
    val route = when (ecran) {
        "reservation", "avis" -> reference?.let { Routes.billet(it) }
        "pro_reservation" -> reference?.let { Routes.reservationPro(it) }
        "parrainage" -> "parrainage"
        "solde" -> if (espacePro) "pro/revenus" else Routes.AGENT
        "agent" -> Routes.AGENT
        "pro_logement" -> id?.let { "pro/logement/$it" } ?: "pro/logements"
        "pro_accueil" -> Routes.PRO_ACCUEIL
        else -> null
    }
    when {
        route != null -> nav.navigate(route) { launchSingleTop = true }
        lien != null && naviguerDepuisLien(nav, lien) -> Unit
        else -> nav.navigate("notifications") { launchSingleTop = true }
    }
}

/** Ouvre l'écran correspondant à un lien du site ; false si le lien n'a pas d'écran dans l'application. */
private fun naviguerDepuisLien(nav: NavHostController, lien: String): Boolean {
    val uri = runCatching { Uri.parse(lien) }.getOrNull()
    val chemin = uri?.path.orEmpty()
    val ref = Regex("VIVI-[A-Z0-9]{6}").find(lien)?.value
    val segments = uri?.pathSegments.orEmpty()
    when {
        ref != null && chemin.startsWith("/mes-reservations") -> nav.navigate(Routes.billet(ref))
        ref != null && chemin.startsWith("/pro") -> nav.navigate(Routes.reservationPro(ref))
        chemin.startsWith("/pro/revenus") -> nav.navigate("pro/revenus")
        chemin.startsWith("/pro/abonnement") -> nav.navigate("pro/abonnement")
        chemin.startsWith("/pro/reservations") -> nav.navigate(Routes.PRO_RESERVATIONS)
        chemin.startsWith("/mon-compte") || chemin.startsWith("/mes-reservations") -> nav.navigate(Routes.MES_RESERVATIONS)
        chemin.startsWith("/parrainage") -> nav.navigate("parrainage")
        chemin.trimEnd('/').isEmpty() && uri?.host != null -> nav.navigate(nav.graph.startDestinationRoute ?: Routes.ACCUEIL) { launchSingleTop = true }
        // Recherche du site : /logements?categorie=salle
        chemin.trimEnd('/') == "/logements" ->
            nav.navigate(if (runCatching { uri?.getQueryParameter("categorie") }.getOrNull() == "salle") Routes.SALLES else Routes.EXPLORER) { launchSingleTop = true }
        segments.size >= 2 && segments[segments.size - 2] == "logements" -> nav.navigate(Routes.logement(segments.last()))
        else -> return false
    }
    return true
}

