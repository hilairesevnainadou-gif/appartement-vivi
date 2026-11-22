@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)

package bj.vivi.app.ui.client

import android.graphics.Bitmap
import android.view.WindowManager
import androidx.activity.compose.LocalActivity
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.Directions
import androidx.compose.material.icons.outlined.EventBusy
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.Luggage
import androidx.compose.material.icons.outlined.Place
import androidx.compose.material.icons.outlined.QrCode2
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.material.icons.outlined.Update
import androidx.compose.material.icons.rounded.Star
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.PrimaryTabRow
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRowDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.OptionsReport
import bj.vivi.app.data.Pub
import bj.vivi.app.data.Reservation
import bj.vivi.app.ui.composants.EspacePub
import bj.vivi.app.ui.composants.rememberSuiviPub
import bj.vivi.app.data.SimulationAnnulation
import bj.vivi.app.data.appel
import bj.vivi.app.data.corps
import bj.vivi.app.ui.AvecEtat
import bj.vivi.app.ui.EcranViewModel
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.Messages
import bj.vivi.app.ui.composants.Bandeau
import bj.vivi.app.ui.composants.BarreTitre
import bj.vivi.app.ui.composants.BoutonContour
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.BoutonSecondaire
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.ChampTexte
import bj.vivi.app.ui.composants.EtatVide
import bj.vivi.app.ui.composants.LigneMontant
import bj.vivi.app.ui.composants.PhotoLogement
import bj.vivi.app.ui.composants.PuceStatut
import bj.vivi.app.ui.composants.Separateur
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.ouvrirLien
import bj.vivi.app.ui.ouvrirPage
import bj.vivi.app.ui.pluriel
import bj.vivi.app.ui.theme.Outfit
import bj.vivi.app.ui.theme.Vivi
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.qrcode.QRCodeWriter
import com.google.zxing.qrcode.decoder.ErrorCorrectionLevel
import java.time.LocalDate

/* ======================================================================
 * Mes réservations : à venir, passées, annulées (comme mes-reservations.html)
 * ====================================================================== */

/* Publicité : seulement dans la liste « Mes réservations », jamais sur le billet. */

class MesReservationsViewModel : EcranViewModel<List<Reservation>>() {
    var onglet by mutableStateOf("avenir")
        private set

    init { recharger() }

    fun choisir(o: String) {
        if (o == onglet) return
        onglet = o
        recharger()
    }

    override suspend fun charger() = appel { mesReservations(onglet) }.map { it.data }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranMesReservations(onReservation: (Reservation) -> Unit, onExplorer: () -> Unit, onRetour: (() -> Unit)? = null, vm: MesReservationsViewModel = viewModel()) {
    LaunchedEffect(Unit) { if (vm.etat is bj.vivi.app.ui.Etat.Pret) vm.recharger(silencieux = true) }
    val pubs = rememberSuiviPub()
    val onglets = listOf("avenir" to "À venir", "passees" to "Passées", "annulees" to "Annulées")
    Scaffold(containerColor = Vivi.Sand, topBar = {
        Column(Modifier.background(Vivi.White)) {
            BarreTitre(if (onRetour != null) "Mes séjours" else "Mes réservations", onRetour)
            PrimaryTabRow(
                selectedTabIndex = onglets.indexOfFirst { it.first == vm.onglet },
                containerColor = Vivi.White, contentColor = Vivi.Teal700,
                indicator = { TabRowDefaults.PrimaryIndicator(Modifier.tabIndicatorOffset(onglets.indexOfFirst { it.first == vm.onglet }), color = Vivi.Teal700, width = 48.dp) },
            ) {
                onglets.forEach { (cle, texte) ->
                    Tab(selected = vm.onglet == cle, onClick = { vm.choisir(cle) }, text = { Text(texte, fontWeight = FontWeight.SemiBold) },
                        selectedContentColor = Vivi.Teal700, unselectedContentColor = Vivi.Muted)
                }
            }
        }
    }) { marges ->
        AvecEtat(vm, Modifier.padding(marges)) { liste ->
            if (liste.isEmpty()) {
                Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState())) {
                    EtatVide(
                        Icons.Outlined.Luggage,
                        when (vm.onglet) { "avenir" -> "Aucun séjour prévu"; "passees" -> "Pas encore de séjour"; else -> "Aucune annulation" },
                        if (vm.onglet == "avenir") "Trouvez un appartement meublé ou une salle pour quelques heures ou quelques jours." else null,
                    ) { if (vm.onglet == "avenir") BoutonPrincipal("Explorer", onExplorer) }
                    EspacePub(Pub.RESERVATIONS, pubs, Modifier.padding(16.dp))
                }
            } else {
                LazyColumn(contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxSize()) {
                    items(liste, key = { it.reference }) { r -> CarteReservation(r) { onReservation(r) } }
                    item { EspacePub(Pub.RESERVATIONS, pubs, Modifier.padding(top = 4.dp)) }
                }
            }
        }
    }
}

@Composable
private fun CarteReservation(r: Reservation, onClick: () -> Unit) {
    Carte(Modifier.fillMaxWidth(), onClick = onClick, remplissage = PaddingValues(12.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            PhotoLogement(r.logement?.photo, null, Modifier.size(84.dp).clip(RoundedCornerShape(12.dp)))
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                PuceStatut(r.statut)
                Spacer(Modifier.height(4.dp))
                Text(r.logement?.titre.orEmpty(), style = MaterialTheme.typography.titleMedium, maxLines = 1)
                Text(Fmt.quand(r.debut), style = MaterialTheme.typography.bodyMedium, color = Vivi.Ink)
                Text(r.avecPersonnes("${r.formule.libelle} · ${r.duree}"), style = MaterialTheme.typography.bodySmall, maxLines = 1)
            }
            if (r.statut.valeur == "confirmee") Icon(Icons.Outlined.QrCode2, contentDescription = "Billet disponible", tint = Vivi.Teal700)
        }
    }
}

/* ======================================================================
 * Billet : QR Code signé par le serveur, code de secours, adresse, actions
 * ====================================================================== */

class BilletViewModel(etatNav: SavedStateHandle) : EcranViewModel<Reservation>() {
    val reference: String = checkNotNull(etatNav["ref"])
    init { recharger() }
    override suspend fun charger() = appel { reservation(reference) }.map { it.data }

    fun avis(note: Int, commentaire: String, fait: () -> Unit) = action {
        appel { donnerAvis(reference, corps("note" to note, "commentaire" to commentaire.trim().ifEmpty { null })) }.map {
            Messages.afficher(it.message ?: "Merci pour votre avis.")
            fait()
            recharger(silencieux = true)
        }
    }
}

@Composable
fun EcranBillet(
    onRetour: () -> Unit,
    onPayer: (String) -> Unit,
    onReporter: (String) -> Unit,
    onAnnuler: (String) -> Unit,
    onLogement: (String) -> Unit,
    vm: BilletViewModel = viewModel(),
) {
    Scaffold(containerColor = Vivi.Sand, topBar = { BarreTitre("Ma réservation", onRetour) }) { marges ->
        AvecEtat(vm, Modifier.padding(marges), squelette = { bj.vivi.app.ui.composants.SqueletteDetail(it) }) { r ->
            if (r.statut.valeur == "en_attente_paiement") LaunchedEffect(Unit) { onPayer(r.reference) }
            Billet(r, vm, onReporter, onAnnuler, onLogement)
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun Billet(r: Reservation, vm: BilletViewModel, onReporter: (String) -> Unit, onAnnuler: (String) -> Unit, onLogement: (String) -> Unit) {
    val context = LocalContext.current
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        // ---------- Billet (confirmation.html) ----------
        Carte(remplissage = PaddingValues(0.dp)) {
            Column {
                Column(Modifier.fillMaxWidth().background(Vivi.Teal900).padding(20.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(r.reference, color = Vivi.Teal200, fontWeight = FontWeight.SemiBold, letterSpacing = 1.sp, modifier = Modifier.weight(1f))
                        PuceStatut(r.statut)
                    }
                    Spacer(Modifier.height(8.dp))
                    Text(r.logement?.titre.orEmpty(), fontFamily = Outfit, fontWeight = FontWeight.SemiBold, fontSize = 22.sp, color = Vivi.White)
                    Spacer(Modifier.height(4.dp))
                    Text(Fmt.jourLong(r.debut), color = Vivi.White, fontWeight = FontWeight.SemiBold)
                    Text(r.creneau, color = Vivi.Teal100, fontSize = 14.sp)
                }
                val billet = r.billet
                if (billet != null) {
                    GarderEcranLumineux()
                    Column(Modifier.fillMaxWidth().padding(20.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                        QrCode(billet.qrContenu, Modifier.size(232.dp))
                        Spacer(Modifier.height(12.dp))
                        Text("Présentez ce QR Code à votre arrivée.", style = MaterialTheme.typography.bodyMedium, textAlign = TextAlign.Center)
                        Spacer(Modifier.height(16.dp))
                        Text("Code de secours", style = MaterialTheme.typography.bodySmall)
                        Text(
                            billet.codeSecours.chunked(3).joinToString(" "),
                            fontFamily = Outfit, fontWeight = FontWeight.Bold, fontSize = 30.sp, letterSpacing = 4.sp, color = Vivi.Ink,
                            modifier = Modifier.semantics { contentDescription = "Code de secours : " + billet.codeSecours.toList().joinToString(" ") },
                        )
                        Text("À donner si le QR Code ne peut pas être lu.", style = MaterialTheme.typography.bodySmall)
                    }
                } else if (r.statut.valeur == "terminee" || r.statut.valeur == "consommee") {
                    Bandeau(
                        if (r.statut.valeur == "terminee") "Séjour terminé. Merci d’avoir choisi VIVI !" else "Vous ne vous êtes pas présenté : la réservation est consommée.",
                        Modifier.padding(16.dp), ton = TonPuce.Neutre, icone = Icons.Outlined.Info,
                    )
                }
            }
        }

        // ---------- Adresse (seulement après paiement) ----------
        r.logement?.adresse?.let { adresse ->
            Carte {
                Column {
                    TitreSection("Adresse")
                    Spacer(Modifier.height(8.dp))
                    Row(verticalAlignment = Alignment.Top) {
                        Icon(Icons.Outlined.Place, null, tint = Vivi.Teal700)
                        Spacer(Modifier.width(8.dp))
                        Text(adresse, style = MaterialTheme.typography.bodyLarge, color = Vivi.Ink)
                    }
                    r.logement.itineraire?.let { lien ->
                        Spacer(Modifier.height(12.dp))
                        BoutonSecondaire("Itinéraire", { ouvrirLien(context, lien) }, Modifier.fillMaxWidth(), icone = Icons.Outlined.Directions)
                    }
                }
            }
        }

        // ---------- Actions ----------
        if (r.actions.reporter || r.actions.annuler) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                if (r.actions.reporter) BoutonContour("Reporter", { onReporter(r.reference) }, Modifier.weight(1f), icone = Icons.Outlined.Update)
                if (r.actions.annuler) BoutonContour("Annuler", { onAnnuler(r.reference) }, Modifier.weight(1f), danger = true, icone = Icons.Outlined.EventBusy)
            }
            if (!r.actions.reporter && r.statut.valeur == "confirmee") {
                Text("Le report n’est plus possible à moins de ${r.delaiReportHeures} h de l’arrivée.", style = MaterialTheme.typography.bodySmall)
            }
        }

        // ---------- Avis ----------
        if (r.actions.donnerAvis) FormulaireAvis(vm)
        r.avis?.let { a ->
            Carte {
                Column {
                    TitreSection("Votre avis")
                    Spacer(Modifier.height(6.dp))
                    Etoiles(a.note)
                    a.commentaire?.let { Text(it, style = MaterialTheme.typography.bodyMedium) }
                    a.statut?.let { Spacer(Modifier.height(6.dp)); PuceStatut(it) }
                }
            }
        }

        // ---------- Paiement ----------
        Carte {
            Column {
                TitreSection("Paiement")
                Spacer(Modifier.height(8.dp))
                LigneMontant("Location", Fmt.fcfa(r.montants.location))
                if (r.montants.services > 0) LigneMontant("Services", Fmt.fcfa(r.montants.services))
                r.services.forEach { Text("· ${it.nom}${if (it.quantite > 1) " × ${it.quantite}" else ""}", style = MaterialTheme.typography.bodySmall) }
                LigneMontant("Commission VIVI", Fmt.fcfa(r.montants.commission))
                if (r.montants.remiseAgent > 0) LigneMontant("Remise agent", "− " + Fmt.fcfa(r.montants.remiseAgent), couleur = Vivi.Success)
                if (r.montants.bonus > 0) LigneMontant("Bonus utilisé", "− " + Fmt.fcfa(r.montants.bonus), couleur = Vivi.Success)
                Separateur(Modifier.padding(vertical = 6.dp))
                LigneMontant("Payé" + (r.moyenPaiement?.let { " ($it)" } ?: ""), Fmt.fcfa(r.montants.paye), fort = true)
                if (r.montants.rembourse > 0) LigneMontant("Remboursé", Fmt.fcfa(r.montants.rembourse), couleur = Vivi.Success)
            }
        }
        r.logement?.slug?.takeIf { it.isNotEmpty() }?.let { slug ->
            BoutonContour(if (r.estSalle) "Voir la salle" else "Voir le logement", { onLogement(slug) }, Modifier.fillMaxWidth())
        }
        Spacer(Modifier.height(8.dp))
    }
}

/** Luminosité maximale pendant l'affichage du QR Code, pour faciliter le scan à l'arrivée. */
@Composable
private fun GarderEcranLumineux() {
    val activite = LocalActivity.current ?: return
    DisposableEffect(Unit) {
        val fenetre = activite.window
        val avant = fenetre.attributes.screenBrightness
        fenetre.attributes = fenetre.attributes.apply { screenBrightness = WindowManager.LayoutParams.BRIGHTNESS_OVERRIDE_FULL }
        fenetre.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        onDispose {
            fenetre.attributes = fenetre.attributes.apply { screenBrightness = avant }
            fenetre.clearFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        }
    }
}

/** QR Code généré à partir de billet.qr_contenu (jeton signé VIVI1.…), sans appel réseau. */
@Composable
fun QrCode(contenu: String, modifier: Modifier = Modifier) {
    val image = remember(contenu) {
        val matrice = QRCodeWriter().encode(contenu, BarcodeFormat.QR_CODE, 512, 512, mapOf(EncodeHintType.ERROR_CORRECTION to ErrorCorrectionLevel.M, EncodeHintType.MARGIN to 1))
        val encre = Vivi.Teal900.toArgb()
        val pixels = IntArray(matrice.width * matrice.height) { i -> if (matrice[i % matrice.width, i / matrice.width]) encre else android.graphics.Color.WHITE }
        Bitmap.createBitmap(pixels, matrice.width, matrice.height, Bitmap.Config.ARGB_8888).asImageBitmap()
    }
    Image(image, contentDescription = "QR Code de la réservation", modifier = modifier.clip(RoundedCornerShape(12.dp)).border(1.dp, Vivi.Line, RoundedCornerShape(12.dp)))
}

@Composable
private fun Etoiles(note: Int) {
    Row(Modifier.semantics(mergeDescendants = true) { contentDescription = "$note étoiles sur 5" }) {
        repeat(5) { i -> Icon(Icons.Rounded.Star, null, tint = if (i < note) Vivi.Orange500 else Vivi.Line, modifier = Modifier.size(22.dp)) }
    }
}

@Composable
private fun FormulaireAvis(vm: BilletViewModel) {
    var note by remember { mutableStateOf(0) }
    var commentaire by remember { mutableStateOf("") }
    Carte {
        Column {
            TitreSection("Comment s’est passé votre séjour ?", surtitre = "Votre avis")
            Spacer(Modifier.height(10.dp))
            Row {
                (1..5).forEach { i ->
                    IconButton(onClick = { note = i }, modifier = Modifier.semantics { contentDescription = "$i étoile" + if (i > 1) "s" else "" }) {
                        Icon(Icons.Rounded.Star, null, tint = if (i <= note) Vivi.Orange500 else Vivi.LineStrong, modifier = Modifier.size(34.dp))
                    }
                }
            }
            Spacer(Modifier.height(8.dp))
            ChampTexte(commentaire, { commentaire = it.take(1000) }, "Commentaire (facultatif)", lignes = 3)
            Spacer(Modifier.height(12.dp))
            BoutonSecondaire("Envoyer mon avis", { vm.avis(note, commentaire) { note = 0; commentaire = "" } }, Modifier.fillMaxWidth(), actif = note > 0, enCours = vm.enCours)
            Text("Votre avis est publié après vérification par VIVI.", style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(top = 6.dp))
        }
    }
}

/* ======================================================================
 * Report : nouvelle date dans la fenêtre autorisée (reporter.html)
 * ====================================================================== */

class ReportViewModel(etatNav: SavedStateHandle) : EcranViewModel<OptionsReport>() {
    val reference: String = checkNotNull(etatNav["ref"])
    var date by mutableStateOf<String?>(null)
    var option by mutableStateOf<String?>(null)
    var accord by mutableStateOf(false)
    init { recharger() }

    override suspend fun charger() = appel { optionsReport(reference) }.map { it.data }.onSuccess { o ->
        if (date == null) date = o.jours.firstOrNull { it.options.isNotEmpty() }?.date
    }

    fun confirmer(fait: () -> Unit) = action {
        appel { reporter(reference, corps("date" to date, "option" to option, "accord" to accord)) }.map {
            Messages.afficher(it.message ?: "Réservation reportée.")
            fait()
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun EcranReport(onRetour: () -> Unit, onFait: () -> Unit, vm: ReportViewModel = viewModel()) {
    Scaffold(containerColor = Vivi.Sand, topBar = { BarreTitre("Reporter mon séjour", onRetour) }) { marges ->
        AvecEtat(vm, Modifier.padding(marges), squelette = { bj.vivi.app.ui.composants.SqueletteDetail(it) }) { o ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp).navigationBarsPadding(), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                if (!o.possible) {
                    EtatVide(Icons.Outlined.CalendarMonth, "Report impossible",
                        "Le report n’est possible que jusqu’à ${o.delaiMinimumHeures} h avant l’arrivée, et s’il reste un créneau libre avant le ${Fmt.jourLong(o.limite)}.")
                    return@Column
                }
                Bandeau("Report gratuit, jusqu’au ${Fmt.jourLong(o.limite)}. Une réservation reportée n’est plus remboursable.", ton = TonPuce.Orange, icone = Icons.Outlined.Info)
                Carte {
                    Column {
                        TitreSection("Nouvelle date")
                        Spacer(Modifier.height(10.dp))
                        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            o.jours.forEach { j ->
                                CaseChoix(Fmt.jourCourt(LocalDate.parse(j.date)), choisi = j.date == vm.date, actif = j.options.isNotEmpty()) {
                                    vm.date = j.date; vm.option = null
                                }
                            }
                        }
                    }
                }
                o.jours.firstOrNull { it.date == vm.date }?.let { j ->
                    Carte {
                        Column {
                            TitreSection("Créneau")
                            Spacer(Modifier.height(10.dp))
                            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                j.options.forEach { op -> CaseChoix(op.libelle, choisi = op.valeur == vm.option) { vm.option = op.valeur } }
                            }
                        }
                    }
                }
                Row(
                    Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).toggleable(vm.accord, role = Role.Checkbox) { vm.accord = it },
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Checkbox(vm.accord, null, colors = CheckboxDefaults.colors(checkedColor = Vivi.Teal700))
                    Spacer(Modifier.width(8.dp))
                    Text("Je comprends que mon ancien QR Code ne sera plus valable et que la réservation ne sera plus remboursable.", style = MaterialTheme.typography.bodyMedium)
                }
                BoutonPrincipal("Confirmer le report", { vm.confirmer(onFait) }, Modifier.fillMaxWidth(),
                    actif = vm.date != null && vm.option != null && vm.accord, enCours = vm.enCours, icone = Icons.Outlined.Schedule)
            }
        }
    }
}

/* ======================================================================
 * Annulation : montant remboursé calculé par le serveur (annuler.html)
 * ====================================================================== */

class AnnulationViewModel(etatNav: SavedStateHandle) : EcranViewModel<SimulationAnnulation>() {
    val reference: String = checkNotNull(etatNav["ref"])
    var motif by mutableStateOf("")
    init { recharger() }
    override suspend fun charger() = appel { simulationAnnulation(reference) }.map { it.data }

    fun confirmer(fait: () -> Unit) = action {
        appel { annuler(reference, corps("motif" to motif.trim().ifEmpty { null })) }.map {
            Messages.afficher(it.message ?: "Réservation annulée.")
            fait()
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
fun EcranAnnulation(onRetour: () -> Unit, onFait: () -> Unit, vm: AnnulationViewModel = viewModel()) {
    Scaffold(containerColor = Vivi.Sand, topBar = { BarreTitre("Annuler ma réservation", onRetour) }) { marges ->
        AvecEtat(vm, Modifier.padding(marges), squelette = { bj.vivi.app.ui.composants.SqueletteDetail(it) }) { s ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp).navigationBarsPadding(), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                Carte {
                    Column {
                        TitreSection("Ce qui vous sera remboursé", surtitre = "Politique ${s.politique}")
                        Spacer(Modifier.height(8.dp))
                        Text(s.regle, style = MaterialTheme.typography.bodyMedium)
                        Spacer(Modifier.height(12.dp))
                        LigneMontant("Montant payé", Fmt.fcfa(s.paye))
                        LigneMontant("Commission VIVI", "− " + Fmt.fcfa(s.commission), aide = "Jamais remboursée")
                        if (s.retenueLocation > 0) LigneMontant("Location retenue", "− " + Fmt.fcfa(s.retenueLocation))
                        if (s.bonusRendu > 0) LigneMontant("Rendu sur votre bonus", Fmt.fcfa(s.bonusRendu))
                        Separateur(Modifier.padding(vertical = 6.dp))
                        LigneMontant("Remboursé", Fmt.fcfa(s.rembourse), fort = true, couleur = if (s.rembourse > 0) Vivi.Success else Vivi.Ink)
                        if (s.rembourse > 0) Text("Sous 24 h à 7 jours${s.moyen?.let { ", sur $it" } ?: ""}.", style = MaterialTheme.typography.bodySmall)
                    }
                }
                Text("Pourquoi annulez-vous ? (facultatif)", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf("Changement de programme", "J’ai trouvé ailleurs", "Problème de transport", "Autre").forEach { m ->
                        CaseChoix(m, choisi = vm.motif == m) { vm.motif = if (vm.motif == m) "" else m }
                    }
                }
                BoutonContour("Confirmer l’annulation", { vm.confirmer(onFait) }, Modifier.fillMaxWidth(), danger = true, enCours = vm.enCours)
                BoutonSecondaire("Garder ma réservation", onRetour, Modifier.fillMaxWidth())
            }
        }
    }
}

