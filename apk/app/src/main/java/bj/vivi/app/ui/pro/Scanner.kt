package bj.vivi.app.ui.pro

import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.NoPhotography
import androidx.compose.material.icons.outlined.Pin
import androidx.compose.material.icons.outlined.PlayArrow
import androidx.compose.material.icons.outlined.QrCodeScanner
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.JsonVivi
import bj.vivi.app.data.Reservation
import bj.vivi.app.data.SejourDemarre
import bj.vivi.app.data.VerificationArrivee
import bj.vivi.app.data.appel
import bj.vivi.app.data.corps
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.Messages
import bj.vivi.app.ui.composants.BarreTitre
import bj.vivi.app.ui.composants.BoutonContour
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.BoutonSecondaire
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.ChampTexte
import bj.vivi.app.ui.composants.LigneMontant
import bj.vivi.app.ui.composants.Segmente
import bj.vivi.app.ui.composants.Separateur
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.theme.Vivi
import com.google.mlkit.vision.barcode.BarcodeScannerOptions
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import kotlinx.coroutines.launch
import java.util.concurrent.Executors

/**
 * Accueil du client (pro-scanner.html) : QR Code lu par la caméra, ou code de secours à 6 chiffres.
 * 1. POST /pro/scanner/verifier → fiche de la réservation ou motif du refus ;
 * 2. POST /pro/scanner/demarrer → séjour démarré, location créditée au solde du propriétaire.
 */
class ScannerViewModel : ViewModel() {
    sealed interface Resultat {
        data class Valide(val r: Reservation) : Resultat
        data class Refuse(val titre: String, val message: String) : Resultat
        data class Demarre(val s: SejourDemarre) : Resultat
    }

    var resultat by mutableStateOf<Resultat?>(null)
        private set
    var enCours by mutableStateOf(false)
        private set
    var attendus by mutableStateOf<List<Reservation>>(emptyList())
        private set
    private var derniereLecture = ""

    init { viewModelScope.launch { appel { arrivees() }.onSuccess { attendus = it.data } } }

    fun verifier(saisie: String) {
        val texte = saisie.trim()
        if (enCours || resultat != null || texte.isEmpty() || texte == derniereLecture) return
        derniereLecture = texte
        viewModelScope.launch {
            enCours = true
            appel { verifierArrivee(corps("saisie" to texte)) }
                .onSuccess { v -> resultat = v.data?.let { Resultat.Valide(it) } ?: Resultat.Refuse(v.titre ?: "Code refusé", v.message.orEmpty()) }
                .onFailure { e ->
                    // 422 : refus détaillé {valide:false, titre, message}
                    val refus = (e as? bj.vivi.app.data.ErreurVivi)?.corps?.let { runCatching { JsonVivi.decodeFromJsonElement(VerificationArrivee.serializer(), it) }.getOrNull() }
                    resultat = Resultat.Refuse(refus?.titre ?: "Vérification impossible", refus?.message ?: e.message.orEmpty())
                }
            enCours = false
        }
    }

    fun demarrer(reference: String) {
        if (enCours) return
        viewModelScope.launch {
            enCours = true
            appel { demarrerSejour(corps("reference" to reference)) }
                .onSuccess { resultat = Resultat.Demarre(it.data); attendus = attendus.filterNot { a -> a.reference == reference } }
                .onFailure { e ->
                    val refus = (e as? bj.vivi.app.data.ErreurVivi)?.corps?.let { runCatching { JsonVivi.decodeFromJsonElement(VerificationArrivee.serializer(), it) }.getOrNull() }
                    resultat = Resultat.Refuse(refus?.titre ?: "Séjour non démarré", refus?.message ?: e.message.orEmpty())
                }
            enCours = false
        }
    }

    fun recommencer() {
        resultat = null
        derniereLecture = ""
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranScanner(onRetour: (() -> Unit)?, vm: ScannerViewModel = viewModel()) {
    val context = LocalContext.current
    var mode by remember { mutableStateOf("camera") }
    var code by remember { mutableStateOf("") }
    var autorise by remember { mutableStateOf(ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) }
    val demande = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { autorise = it }
    LaunchedEffect(Unit) { if (!autorise) demande.launch(Manifest.permission.CAMERA) }

    Scaffold(containerColor = Vivi.Sand, topBar = { BarreTitre("Accueillir un client", onRetour) }) { marges ->
        Column(Modifier.padding(marges).fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
            Segmente(listOf("camera" to "QR Code", "code" to "Code de secours"), mode, { mode = it })

            if (mode == "camera") {
                Box(Modifier.fillMaxWidth().aspectRatio(1f).clip(RoundedCornerShape(20.dp)).background(Vivi.Teal900), contentAlignment = Alignment.Center) {
                    if (autorise) {
                        Camera(actif = vm.resultat == null, onLecture = vm::verifier)
                        // Viseur
                        Box(Modifier.fillMaxSize(.66f).border(3.dp, Vivi.Orange400, RoundedCornerShape(20.dp)))
                    } else {
                        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(24.dp)) {
                            Icon(Icons.Outlined.NoPhotography, null, tint = Vivi.Teal200, modifier = Modifier.size(40.dp))
                            Spacer(Modifier.height(12.dp))
                            Text("Autorisez l’appareil photo pour lire le QR Code du client.", color = Vivi.White, textAlign = TextAlign.Center)
                            Spacer(Modifier.height(16.dp))
                            BoutonPrincipal("Autoriser", { demande.launch(Manifest.permission.CAMERA) })
                        }
                    }
                }
                Text("Placez le QR Code du billet dans le cadre : il est lu automatiquement.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth())
            } else {
                Carte {
                    Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                        TitreSection("Code de secours")
                        Text("Le client le trouve sous son QR Code, sur son billet ou dans le SMS de confirmation.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)
                        ChampTexte(code, { code = it.filter(Char::isDigit).take(6) }, "Code à 6 chiffres", clavier = KeyboardType.NumberPassword, icone = Icons.Outlined.Pin)
                        BoutonPrincipal("Vérifier", { vm.recommencer(); vm.verifier(code) }, Modifier.fillMaxWidth(), actif = code.length == 6, enCours = vm.enCours)
                    }
                }
            }

            if (vm.attendus.isNotEmpty()) {
                Carte {
                    Column {
                        TitreSection("Attendus aujourd’hui")
                        vm.attendus.forEach { r ->
                            Separateur()
                            Row(Modifier.fillMaxWidth().padding(vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                                Text(Fmt.heure(r.debut), style = MaterialTheme.typography.titleSmall, modifier = Modifier.width(56.dp))
                                Column(Modifier.weight(1f)) {
                                    Text(r.client?.nom ?: "Client", style = MaterialTheme.typography.titleSmall)
                                    Text(r.logement?.titre.orEmpty(), style = MaterialTheme.typography.bodySmall, maxLines = 1)
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    vm.resultat?.let { res ->
        ModalBottomSheet(onDismissRequest = vm::recommencer, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Vivi.White) {
            Column(Modifier.padding(horizontal = 20.dp).navigationBarsPadding().padding(bottom = 16.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                when (res) {
                    is ScannerViewModel.Resultat.Valide -> {
                        Pastille(Icons.Outlined.CheckCircle, Vivi.SuccessBg, Vivi.Success)
                        Text("Réservation valide", style = MaterialTheme.typography.headlineSmall)
                        Spacer(Modifier.height(16.dp))
                        val r = res.r
                        Carte(Modifier.fillMaxWidth()) {
                            Column {
                                LigneMontant("Client", r.avecPersonnes(r.client?.nom.orEmpty()))
                                LigneMontant(if (r.estSalle) "Salle" else "Logement", r.logement?.titre.orEmpty())
                                LigneMontant("Créneau", "${Fmt.jourCourt(r.debut)} · ${r.creneau}")
                                LigneMontant("Services", r.servicesTexte ?: "Aucun service")
                                LigneMontant("Référence", r.reference)
                            }
                        }
                        Spacer(Modifier.height(16.dp))
                        BoutonPrincipal("Démarrer le séjour", { vm.demarrer(r.reference) }, Modifier.fillMaxWidth(), enCours = vm.enCours, icone = Icons.Outlined.PlayArrow)
                        Spacer(Modifier.height(8.dp))
                        BoutonContour("Annuler", vm::recommencer, Modifier.fillMaxWidth())
                    }
                    is ScannerViewModel.Resultat.Refuse -> {
                        Pastille(Icons.Outlined.ErrorOutline, Vivi.DangerBg, Vivi.Danger)
                        Text(res.titre, style = MaterialTheme.typography.headlineSmall, textAlign = TextAlign.Center)
                        Spacer(Modifier.height(8.dp))
                        Text(res.message, style = MaterialTheme.typography.bodyLarge, color = Vivi.Muted, textAlign = TextAlign.Center)
                        Spacer(Modifier.height(20.dp))
                        BoutonSecondaire("Scanner un autre code", { code = ""; vm.recommencer() }, Modifier.fillMaxWidth(), icone = Icons.Outlined.QrCodeScanner)
                    }
                    is ScannerViewModel.Resultat.Demarre -> {
                        Pastille(Icons.Outlined.CheckCircle, Vivi.SuccessBg, Vivi.Success)
                        Text("Séjour démarré à ${Fmt.heure(res.s.heure)}", style = MaterialTheme.typography.headlineSmall, textAlign = TextAlign.Center)
                        Spacer(Modifier.height(8.dp))
                        Text("Bienvenue à ${res.s.client} dans « ${res.s.logement} ».", style = MaterialTheme.typography.bodyLarge, color = Vivi.Muted, textAlign = TextAlign.Center)
                        res.s.credit?.let {
                            Spacer(Modifier.height(12.dp))
                            Text("${Fmt.fcfa(it)} ajoutés au solde du propriétaire.", style = MaterialTheme.typography.titleMedium, color = Vivi.Success)
                        }
                        Spacer(Modifier.height(20.dp))
                        BoutonPrincipal("Terminé", { code = ""; vm.recommencer(); Messages.afficher("Séjour démarré.") }, Modifier.fillMaxWidth())
                    }
                }
            }
        }
    }
}

@Composable
private fun Pastille(icone: androidx.compose.ui.graphics.vector.ImageVector, fond: androidx.compose.ui.graphics.Color, teinte: androidx.compose.ui.graphics.Color) {
    Box(Modifier.size(72.dp).clip(CircleShape).background(fond), contentAlignment = Alignment.Center) {
        Icon(icone, null, tint = teinte, modifier = Modifier.size(36.dp))
    }
    Spacer(Modifier.height(12.dp))
}

/** Aperçu CameraX et lecture des QR Codes par ML Kit (sur l'appareil, sans envoi d'image). */
@Composable
private fun Camera(actif: Boolean, onLecture: (String) -> Unit) {
    val context = LocalContext.current
    val cycle = LocalLifecycleOwner.current
    val executeur = remember { Executors.newSingleThreadExecutor() }
    val lecteur = remember { BarcodeScanning.getClient(BarcodeScannerOptions.Builder().setBarcodeFormats(Barcode.FORMAT_QR_CODE).build()) }
    val actifActuel = androidx.compose.runtime.rememberUpdatedState(actif)
    val surLecture = androidx.compose.runtime.rememberUpdatedState(onLecture)

    DisposableEffect(Unit) { onDispose { lecteur.close(); executeur.shutdown() } }

    AndroidView(
        modifier = Modifier.fillMaxSize(),
        factory = { ctx ->
            val vue = PreviewView(ctx).apply { scaleType = PreviewView.ScaleType.FILL_CENTER }
            val fournisseur = ProcessCameraProvider.getInstance(ctx)
            fournisseur.addListener({
                val camera = fournisseur.get()
                val apercu = Preview.Builder().build().also { it.setSurfaceProvider(vue.surfaceProvider) }
                val analyse = ImageAnalysis.Builder().setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST).build()
                analyse.setAnalyzer(executeur) { image ->
                    val media = image.image
                    if (media == null || !actifActuel.value) {
                        image.close()
                        return@setAnalyzer
                    }
                    lecteur.process(InputImage.fromMediaImage(media, image.imageInfo.rotationDegrees))
                        .addOnSuccessListener { codes -> codes.firstNotNullOfOrNull { it.rawValue }?.let { v -> ContextCompat.getMainExecutor(ctx).execute { surLecture.value(v) } } }
                        .addOnCompleteListener { image.close() }
                }
                runCatching {
                    camera.unbindAll()
                    camera.bindToLifecycle(cycle, CameraSelector.DEFAULT_BACK_CAMERA, apercu, analyse)
                }.onFailure { Messages.erreur("Caméra indisponible : saisissez le code de secours.") }
            }, ContextCompat.getMainExecutor(context))
            vue
        },
    )
}
