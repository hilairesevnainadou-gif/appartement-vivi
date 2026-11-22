@file:OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)

package bj.vivi.app.ui.pro

import android.Manifest
import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
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
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AddAPhoto
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.automirrored.outlined.ArrowForward
import androidx.compose.material.icons.outlined.Badge
import androidx.compose.foundation.selection.selectable
import androidx.compose.material.icons.outlined.Campaign
import androidx.compose.material.icons.outlined.SupportAgent
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.Groups
import androidx.compose.material.icons.outlined.Kitchen
import androidx.compose.material.icons.outlined.Payments
import androidx.compose.material.icons.outlined.PhotoLibrary
import androidx.compose.material.icons.outlined.Place
import androidx.compose.material.icons.outlined.TaskAlt
import androidx.compose.animation.togetherWith
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.Check
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.MyLocation
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.material.icons.outlined.Star
import androidx.compose.material.icons.outlined.StarOutline
import androidx.compose.material.icons.outlined.Visibility
import androidx.compose.material.icons.outlined.WbSunny
import androidx.compose.material.icons.outlined.WbTwilight
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TimePicker
import androidx.compose.material3.TimePickerDefaults
import androidx.compose.material3.rememberTimePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.Catalogue
import bj.vivi.app.data.Configuration
import bj.vivi.app.data.Enumeration
import bj.vivi.app.data.FicheLogementPro
import bj.vivi.app.data.FicheLogementPro.TarifPro
import bj.vivi.app.data.Position
import bj.vivi.app.data.appel
import bj.vivi.app.data.corps
import bj.vivi.app.ui.EcranViewModel
import bj.vivi.app.ui.AvecEtat
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.Messages
import bj.vivi.app.ui.composants.Bandeau
import bj.vivi.app.ui.composants.BarreAction
import bj.vivi.app.ui.composants.BarreTitre
import bj.vivi.app.ui.composants.BoutonContour
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.BoutonTexte
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.ChampTexte
import bj.vivi.app.ui.composants.Compteur
import bj.vivi.app.ui.composants.PhotoLogement
import bj.vivi.app.ui.composants.PuceStatut
import bj.vivi.app.ui.composants.Segmente
import bj.vivi.app.ui.composants.Separateur
import bj.vivi.app.ui.composants.SqueletteDetail
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.donnees
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonPrimitive
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.ByteArrayOutputStream

/*
 * Ajout et modification d'un logement par son propriétaire (POST · PUT /pro/logements, photos en multipart).
 * Même formulaire que l'espace propriétaire du site ; le serveur valide (App\Http\Requests\LogementRequest)
 * et renvoie ses erreurs par champ (« tarifs.heure.prix », « creneaux.0.nom »…), affichées sous chaque champ.
 */

class EditionLogementViewModel(etatNav: SavedStateHandle) : EcranViewModel<FicheLogementPro>() {
    /** null : nouveau logement. */
    val id: Long? = etatNav.get<Long>("id")
    var f by mutableStateOf(FicheLogementPro())
        private set
    /** Saisie libre de la part marketing (« 10 » ou « 2500 »). */
    var partTexte by mutableStateOf("")
    var erreurs by mutableStateOf<Map<String, String>>(emptyMap())
        private set
    var envoiPhotos by mutableStateOf(false)
        private set
    /** Étape affichée (0 à 7). Après la création, on arrive directement sur les photos (?etape=photos). */
    var etape by mutableStateOf(if (etatNav.get<String>("etape") == "photos") ETAPE_FIN else 0)
        private set
    /** Logement qui vient d'être créé : bandeau de félicitations sur l'étape photos. */
    val vientDEtreCree = etatNav.get<String>("etape") == "photos"

    init { recharger() }

    fun allerA(e: Int) { etape = e.coerceIn(0, ETAPE_FIN) }

    /** « Suivant » : contrôle les champs de l'étape avant d'avancer. */
    fun suivant(): Boolean {
        val e = verifier().filterKeys { etapeDe(it) == etape }
        if (e.isNotEmpty()) {
            erreurs = erreurs + e
            Messages.erreur("Complétez les champs signalés en rouge.")
            return false
        }
        allerA(etape + 1)
        return true
    }

    fun precedent() = allerA(etape - 1)

    /** Étapes dont un champ est en erreur (pastille rouge dans le fil d'étapes). */
    val etapesEnErreur get() = erreurs.keys.map(::etapeDe).toSet()

    companion object {
        const val ETAPE_FIN = 7

        /** Étape du formulaire qui contient un champ (erreurs renvoyées par le serveur). */
        fun etapeDe(champ: String): Int = when {
            champ in setOf("titre", "type", "description") -> 0
            champ in setOf("ville", "quartier", "adresse", "latitude", "longitude") -> 1
            champ in setOf("capacite", "chambres", "lits", "salles_de_bain", "unites", "temps_preparation_minutes") -> 2
            champ.startsWith("tarifs") || champ.startsWith("creneaux") -> 3
            champ.startsWith("equipements") || champ.startsWith("services") -> 4
            champ == "mode_gestion" -> 5
            champ.startsWith("marketing") -> 6
            else -> -1
        }
    }

    override suspend fun charger(): Result<FicheLogementPro> {
        val config = Catalogue.obtenir()
        val r = if (id == null) Result.success(nouveau(config)) else appel { ficheLogementPro(id) }.map { it.data }
        return r.onSuccess { fiche ->
            f = fiche
            partTexte = fiche.marketingValeur?.let { if (it % 1.0 == 0.0) it.toLong().toString() else it.toString() }.orEmpty()
        }
    }

    private fun nouveau(c: Configuration?) = FicheLogementPro(
        type = c?.typesLogement?.firstOrNull(),
        ville = c?.villes?.firstOrNull().orEmpty(),
        tarifs = FicheLogementPro.Tarifs(heure = TarifPro(actif = true, heureOuverture = "08:00", heureFermeture = "22:00", dureeMinimumHeures = 1)),
    )

    fun modifier(champs: Collection<String> = emptyList(), bloc: FicheLogementPro.() -> FicheLogementPro) {
        f = f.bloc()
        if (champs.any { it in erreurs }) erreurs = erreurs - champs.toSet()
    }

    fun tarif(formule: String, bloc: TarifPro.() -> TarifPro) = modifier(erreurs.keys.filter { it.startsWith("tarifs") }) {
        copy(tarifs = when (formule) {
            "heure" -> tarifs.copy(heure = tarifs.heure.bloc())
            "demi_journee" -> tarifs.copy(demiJournee = tarifs.demiJournee.bloc())
            else -> tarifs.copy(journee = tarifs.journee.bloc())
        })
    }

    fun activer(formule: String, actif: Boolean) = tarif(formule) {
        when {
            !actif -> copy(actif = false)
            formule == "heure" -> copy(actif = true, heureOuverture = heureOuverture ?: "08:00", heureFermeture = heureFermeture ?: "22:00", dureeMinimumHeures = dureeMinimumHeures ?: 1)
            // Journée d'une salle : début et fin le jour même ; d'un logement : départ le lendemain
            formule == "journee" -> estSalle(f.type).let { s ->
                copy(actif = true, heureArrivee = heureArrivee ?: if (s) "08:00" else "14:00", heureDepart = heureDepart ?: if (s) "22:00" else "12:00")
            }
            else -> copy(actif = true)
        }
    }.also {
        // Demi-journée sans créneau : deux créneaux proposés, modifiables
        if (formule == "demi_journee" && actif && f.creneaux.isEmpty()) modifier(listOf("creneaux")) {
            copy(creneaux = listOf(
                FicheLogementPro.Creneau(nom = "Matin", heureDebut = "08:00", heureFin = "13:00"),
                FicheLogementPro.Creneau(nom = "Après-midi", heureDebut = "14:00", heureFin = "19:00"),
            ))
        }
    }

    fun enregistrer(surCree: (Long) -> Unit) {
        val locales = verifier()
        if (locales.isNotEmpty()) {
            erreurs = locales
            locales.keys.map(::etapeDe).filter { it >= 0 }.minOrNull()?.let(::allerA)
            Messages.erreur("Vérifiez les champs signalés en rouge.")
            return
        }
        val c = construireCorps()
        action(surErreur = { e ->
            erreurs = e.champs.ifEmpty { mapOf("general" to e.message) }
            // Retour à la première étape concernée par une erreur du serveur
            e.champs.keys.map(::etapeDe).filter { it >= 0 }.minOrNull()?.let(::allerA)
            Messages.erreur(e.message)
        }) {
            val r = if (id == null) appel { creerLogement(c) } else appel { modifierLogement(id, c) }
            r.map { rep ->
                erreurs = emptyMap()
                remplacer(rep.data)
                f = rep.data
                Messages.afficher(rep.message ?: "Logement enregistré.")
                if (id == null) surCree(rep.data.id)
            }
        }
    }

    /** Contrôles évidents avant l'envoi ; le serveur reste juge des règles complètes. */
    private fun verifier(): Map<String, String> = buildMap {
        if (f.titre.trim().length < 4) put("titre", "Donnez un nom d’au moins 4 caractères.")
        if (f.quartier.isNullOrBlank()) put("quartier", "Indiquez le quartier.")
        val t = f.tarifs
        if (!t.heure.actif && !t.demiJournee.actif && !t.journee.actif) put("tarifs", "Activez au moins une formule : à l’heure, à la demi-journée ou à la journée.")
        if (t.heure.actif && (t.heure.prix ?: 0) < 500) put("tarifs.heure.prix", "500 FCFA au moins.")
        if (t.demiJournee.actif && (t.demiJournee.prix ?: 0) < 500) put("tarifs.demi_journee.prix", "500 FCFA au moins.")
        if (t.journee.actif && (t.journee.prix ?: 0) < 500) put("tarifs.journee.prix", "500 FCFA au moins.")
        if (t.demiJournee.actif && f.creneaux.isEmpty()) put("creneaux", "Ajoutez au moins un créneau.")
        f.creneaux.forEachIndexed { i, cr -> if (cr.nom.isBlank()) put("creneaux.$i.nom", "Nommez le créneau.") }
        if (f.marketingType != null && partTexte.replace(',', '.').toDoubleOrNull() == null) put("marketing_valeur", "Indiquez la part de l’agent.")
    }

    private fun construireCorps() = corps(
        "titre" to f.titre.trim(),
        "type" to f.type?.valeur,
        "ville" to f.ville,
        "quartier" to f.quartier?.trim(),
        "adresse" to f.adresse?.trim()?.ifEmpty { null },
        "latitude" to f.latitude,
        "longitude" to f.longitude,
        "description" to f.description?.trim()?.ifEmpty { null },
        "capacite" to f.capacite,
        "chambres" to f.chambres,
        "unites" to f.unites,
        "lits" to f.lits,
        "salles_de_bain" to f.sallesDeBain,
        "temps_preparation_minutes" to f.tempsPreparation,
        "equipements" to f.equipements,
        "services" to f.services.map { it.id },
        "mode_gestion" to f.modeGestion?.valeur?.ifBlank { null },
        // null explicite : « aucune part marketing » (un champ absent laisserait la part inchangée)
        "marketing_type" to (f.marketingType?.let { JsonPrimitive(it) } ?: JsonNull),
        "marketing_valeur" to (if (f.marketingType == null) JsonNull else partTexte.replace(',', '.').toDoubleOrNull()?.let { JsonPrimitive(it) }),
        "tarifs" to mapOf(
            "heure" to with(f.tarifs.heure) {
                mapOf("actif" to actif, "prix" to prix, "heure_ouverture" to heureOuverture, "heure_fermeture" to heureFermeture, "duree_minimum_heures" to dureeMinimumHeures).filterValues { it != null }
            },
            "demi_journee" to with(f.tarifs.demiJournee) { mapOf("actif" to actif, "prix" to prix).filterValues { it != null } },
            "journee" to with(f.tarifs.journee) {
                mapOf("actif" to actif, "prix" to prix, "heure_arrivee" to heureArrivee, "heure_depart" to heureDepart).filterValues { it != null }
            },
        ),
        "creneaux" to f.creneaux.map { mapOf("id" to it.id, "nom" to it.nom.trim(), "heure_debut" to it.heureDebut, "heure_fin" to it.heureFin).filterValues { v -> v != null } },
    )

    /* ---------- Photos ---------- */

    fun ajouterPhotos(context: Context, uris: List<Uri>) {
        val logement = id ?: return
        if (uris.isEmpty()) return
        viewModelScope.launch {
            envoiPhotos = true
            val (parties, refusees) = withContext(Dispatchers.IO) { preparer(context, uris) }
            if (refusees > 0) Messages.info(if (refusees > 1) "$refusees photos trop petites ignorées (800 × 600 pixels au minimum)." else "Une photo trop petite ignorée (800 × 600 pixels au minimum).")
            if (parties.isNotEmpty()) {
                appel { ajouterPhotos(logement, parties) }
                    .onSuccess { r -> f = f.copy(photos = r.data.photos); remplacer(f); Messages.afficher(r.message ?: "Photos ajoutées.") }
                    .onFailure { Messages.erreur(it.message ?: "") }
            }
            envoiPhotos = false
        }
    }

    fun supprimerPhoto(photo: Long) = photoAction { supprimerPhoto(id!!, photo) }
    fun photoPrincipale(photo: Long) = photoAction { photoPrincipale(id!!, photo) }

    private fun photoAction(bloc: suspend bj.vivi.app.data.ViviApi.() -> bj.vivi.app.data.Enveloppe<FicheLogementPro>) {
        if (id == null) return
        viewModelScope.launch {
            envoiPhotos = true
            appel(bloc).onSuccess { r -> f = f.copy(photos = r.data.photos); Messages.afficher(r.message ?: "") }.onFailure { Messages.erreur(it.message ?: "") }
            envoiPhotos = false
        }
    }

    /** Photos réenregistrées en JPEG (2048 px au plus, qualité 85) : moins de 5 Mo, envoi rapide en 3G. */
    private fun preparer(context: Context, uris: List<Uri>): Pair<List<MultipartBody.Part>, Int> {
        var refusees = 0
        val parties = uris.mapIndexedNotNull { i, uri ->
            val bornes = BitmapFactory.Options().apply { inJustDecodeBounds = true }
            context.contentResolver.openInputStream(uri)?.use { BitmapFactory.decodeStream(it, null, bornes) }
            val (w, h) = bornes.outWidth to bornes.outHeight
            if (maxOf(w, h) < 800 || minOf(w, h) < 600) { refusees++; return@mapIndexedNotNull null }
            var echantillon = 1
            while (maxOf(w, h) / (echantillon * 2) >= 2048) echantillon *= 2
            val image = context.contentResolver.openInputStream(uri)?.use {
                BitmapFactory.decodeStream(it, null, BitmapFactory.Options().apply { inSampleSize = echantillon })
            } ?: return@mapIndexedNotNull null
            val echelle = minOf(1f, 2048f / maxOf(image.width, image.height))
            val finale = if (echelle < 1f) Bitmap.createScaledBitmap(image, (image.width * echelle).toInt(), (image.height * echelle).toInt(), true) else image
            if (finale.width < 800 && finale.height < 800 || minOf(finale.width, finale.height) < 600) { refusees++; return@mapIndexedNotNull null }
            val sortie = ByteArrayOutputStream()
            finale.compress(Bitmap.CompressFormat.JPEG, 85, sortie)
            MultipartBody.Part.createFormData("photos[]", "photo-${i + 1}.jpg", sortie.toByteArray().toRequestBody("image/jpeg".toMediaType()))
        }
        return parties to refusees
    }
}

/** Les étapes de l'assistant : titre, icône, phrase d'aide. La dernière dépend du mode (récapitulatif ou photos). */
private data class InfoEtape(val titre: String, val icone: ImageVector, val aide: String)

private fun etapes(nouveau: Boolean) = listOf(
    InfoEtape("Informations", Icons.Outlined.Badge, "Le nom et le type que verront les clients."),
    InfoEtape("Emplacement", Icons.Outlined.Place, "Ville, quartier et position : le client voit une zone de 400 m, l’adresse exacte seulement sur son billet."),
    InfoEtape("Capacité", Icons.Outlined.Groups, "Combien de personnes, de chambres et de lits."),
    InfoEtape("Formules et tarifs", Icons.Outlined.Payments, "Choisissez comment louer : à l’heure, à la demi-journée, à la journée."),
    InfoEtape("Équipements", Icons.Outlined.Kitchen, "Ce que le logement offre, et les services à la carte proposés."),
    InfoEtape("Gestion", Icons.Outlined.SupportAgent, "Qui accueille les clients et scanne leur QR Code : vous, ou l’équipe VIVI."),
    InfoEtape("Agent marketing", Icons.Outlined.Campaign, "La part versée à l’agent qui vous amène un client."),
    if (nouveau) InfoEtape("Récapitulatif", Icons.Outlined.TaskAlt, "Vérifiez puis créez le logement. Les photos s’ajoutent juste après.")
    else InfoEtape("Photos", Icons.Outlined.PhotoLibrary, "800 × 600 pixels au minimum ; la première est la photo principale."),
)

@Composable
fun EcranEditionLogement(
    id: Long?,
    onRetour: () -> Unit,
    onCree: (Long) -> Unit = {},
    onApercu: (String) -> Unit = {},
    vm: EditionLogementViewModel = viewModel(),
) {
    val nouveau = id == null
    val liste = etapes(nouveau)
    val derniere = EditionLogementViewModel.ETAPE_FIN
    // Retour système : étape précédente plutôt que de quitter le formulaire
    androidx.activity.compose.BackHandler(enabled = vm.etape > 0 && !vm.vientDEtreCree) { vm.precedent() }

    Scaffold(
        containerColor = Vivi.Sand,
        topBar = {
            Column(Modifier.background(Vivi.White)) {
                BarreTitre(if (nouveau) "Nouveau logement" else vm.f.titre.ifBlank { "Modifier le logement" }, onRetour, actions = {
                    if (!nouveau && vm.f.statut.valeur == "publie") {
                        IconButton(onClick = { onApercu(vm.f.slug) }) { Icon(Icons.Outlined.Visibility, contentDescription = "Voir la fiche publique", tint = Vivi.Teal700) }
                    }
                })
                if (vm.etat.donnees != null) FilEtapes(liste, vm.etape, vm.etapesEnErreur, libre = !nouveau, onEtape = vm::allerA)
            }
        },
        bottomBar = {
            if (vm.etat.donnees != null) BarreAction(Modifier.navigationBarsPadding().imePadding()) {
                when {
                    // Création : Précédent / Suivant, puis « Créer le logement » sur le récapitulatif
                    nouveau -> {
                        if (vm.etape > 0) BoutonContour("Précédent", vm::precedent, icone = Icons.AutoMirrored.Outlined.ArrowBack)
                        if (vm.etape < derniere) BoutonPrincipal("Suivant", { vm.suivant() }, Modifier.weight(1f), icone = Icons.AutoMirrored.Outlined.ArrowForward)
                        else BoutonPrincipal("Créer le logement", { vm.enregistrer(onCree) }, Modifier.weight(1f), enCours = vm.enCours, icone = Icons.Outlined.Check)
                    }
                    // Juste créé, étape photos : terminer
                    vm.vientDEtreCree -> BoutonPrincipal("Terminer", onRetour, Modifier.weight(1f), icone = Icons.Outlined.Check)
                    // Modification : enregistrer à tout moment, flèches pour passer d'une étape à l'autre
                    else -> {
                        IconButton(onClick = vm::precedent, enabled = vm.etape > 0) {
                            Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Étape précédente", tint = if (vm.etape > 0) Vivi.Teal700 else Vivi.LineStrong)
                        }
                        if (vm.etape < derniere) {
                            BoutonPrincipal("Enregistrer", { vm.enregistrer {} }, Modifier.weight(1f), enCours = vm.enCours, icone = Icons.Outlined.Check)
                            IconButton(onClick = { vm.allerA(vm.etape + 1) }) {
                                Icon(Icons.AutoMirrored.Outlined.ArrowForward, contentDescription = "Étape suivante", tint = Vivi.Teal700)
                            }
                        } else {
                            BoutonPrincipal("Terminé", onRetour, Modifier.weight(1f), icone = Icons.Outlined.Check)
                        }
                    }
                }
            }
        },
    ) { marges ->
        AvecEtat(vm, Modifier.padding(marges), actualisable = false, squelette = { SqueletteDetail(it) }) { _ ->
            androidx.compose.animation.AnimatedContent(
                vm.etape, label = "etape",
                transitionSpec = {
                    val sens = if (targetState > initialState) 1 else -1
                    (androidx.compose.animation.slideInHorizontally { it * sens / 4 } + androidx.compose.animation.fadeIn()) togetherWith
                        (androidx.compose.animation.slideOutHorizontally { -it * sens / 4 } + androidx.compose.animation.fadeOut())
                },
            ) { etape ->
                Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                    EnTeteEtape(liste[etape], etape, liste.size)
                    if (etape == 0) EnTeteStatut(vm, nouveau)
                    vm.erreurs["general"]?.let { Bandeau(it, ton = TonPuce.Danger, icone = Icons.Outlined.ErrorOutline) }
                    when (etape) {
                        0 -> EtapeInformations(vm)
                        1 -> EtapeEmplacement(vm)
                        2 -> EtapeCapacite(vm)
                        3 -> EtapeTarifs(vm)
                        4 -> EtapeEquipements(vm)
                        5 -> EtapeGestion(vm)
                        6 -> EtapeMarketing(vm)
                        else -> if (nouveau) EtapeRecapitulatif(vm) else {
                            if (vm.vientDEtreCree) Bandeau("Votre logement est créé et envoyé à VIVI pour vérification. Ajoutez maintenant de belles photos : ce sont elles qui donnent envie de réserver.",
                                ton = TonPuce.Succes, icone = Icons.Outlined.CheckCircle)
                            Carte { Column(verticalArrangement = Arrangement.spacedBy(12.dp)) { Photos(vm) } }
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                }
            }
        }
    }
}

/** Fil d'étapes défilant : numéro (ou ✓), pastille rouge si une erreur s'y trouve. En modification, toutes les étapes sont accessibles. */
@Composable
private fun FilEtapes(liste: List<InfoEtape>, courante: Int, enErreur: Set<Int>, libre: Boolean, onEtape: (Int) -> Unit) {
    Column {
        androidx.compose.material3.LinearProgressIndicator(
            progress = { (courante + 1f) / liste.size }, modifier = Modifier.fillMaxWidth().height(3.dp),
            color = Vivi.Teal700, trackColor = Vivi.Line, drawStopIndicator = {},
        )
        androidx.compose.foundation.lazy.LazyRow(
            contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp), horizontalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            items(liste.size) { i ->
                val accessible = libre || i <= courante
                val actif = i == courante
                val fait = i < courante
                Row(
                    Modifier.clip(RoundedCornerShape(999.dp))
                        .background(if (actif) Vivi.Teal50 else Color.Transparent)
                        .clickable(enabled = accessible && !actif, role = Role.Tab) { onEtape(i) }
                        .padding(horizontal = 10.dp, vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Box(
                        Modifier.size(24.dp).clip(CircleShape).background(when {
                            i in enErreur -> Vivi.Danger
                            actif || fait -> Vivi.Teal700
                            else -> Vivi.Segmented
                        }),
                        contentAlignment = Alignment.Center,
                    ) {
                        if (fait && i !in enErreur) Icon(Icons.Outlined.Check, null, tint = Vivi.White, modifier = Modifier.size(14.dp))
                        else Text("${i + 1}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = if (actif || i in enErreur) Vivi.White else Vivi.Muted)
                    }
                    Spacer(Modifier.width(6.dp))
                    Text(liste[i].titre, fontSize = 13.sp, fontWeight = if (actif) FontWeight.SemiBold else FontWeight.Normal,
                        color = when { i in enErreur -> Vivi.Danger; actif -> Vivi.Teal800; accessible -> Vivi.Body; else -> Vivi.Muted })
                }
            }
        }
        Separateur()
    }
}

@Composable
private fun EnTeteEtape(info: InfoEtape, etape: Int, total: Int) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(48.dp).clip(RoundedCornerShape(14.dp)).background(Vivi.Teal900), contentAlignment = Alignment.Center) {
            Icon(info.icone, null, tint = Vivi.White, modifier = Modifier.size(24.dp))
        }
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Text("ÉTAPE ${etape + 1} SUR $total", color = Vivi.Teal700, fontSize = 12.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
            Text(info.titre, style = MaterialTheme.typography.headlineSmall)
        }
    }
    Text(info.aide, style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)
}

@Composable
private fun EnTeteStatut(vm: EditionLogementViewModel, nouveau: Boolean) {
    val f = vm.f
    if (nouveau) {
        Bandeau("Sept étapes rapides, puis les photos. L’annonce est vérifiée par VIVI avant d’être visible des clients.", ton = TonPuce.Teal, icone = Icons.Outlined.Info)
        return
    }
    Row(verticalAlignment = Alignment.CenterVertically) {
        PuceStatut(f.statut)
        Spacer(Modifier.width(8.dp))
        if (f.statut.valeur == "publie") Text(if (f.enLigne) "En ligne" else "En pause", style = MaterialTheme.typography.bodySmall)
    }
    f.motifRefus?.let { Bandeau("Refusé par VIVI : $it. Corrigez puis enregistrez pour une nouvelle vérification.", ton = TonPuce.Danger, icone = Icons.Outlined.ErrorOutline) }
}

/* ---------- Étapes ---------- */

@Composable
private fun Bloc(contenu: @Composable () -> Unit) = Carte { Column(verticalArrangement = Arrangement.spacedBy(12.dp)) { contenu() } }

@Composable
private fun EtapeInformations(vm: EditionLogementViewModel) {
    val f = vm.f
    val e = vm.erreurs
    Bloc {
        ChampTexte(f.titre, { v -> vm.modifier(listOf("titre")) { copy(titre = v.take(120)) } }, if (estSalle(f.type)) "Nom de la salle" else "Nom du logement",
            indication = if (estSalle(f.type)) "Ex. Salle Les Cocotiers" else "Ex. Studio Lagune climatisé", aide = "${f.titre.length} / 120 · un nom court et parlant", erreur = e["titre"])
        // Logements d'un côté, salles de fêtes et de conférence de l'autre
        val types = Catalogue.configuration?.typesLogement.orEmpty()
        val groupes = types.groupBy { it.categorie ?: "logement" }
        listOf("logement" to "Type de logement", "salle" to "Ou une salle pour événements").forEach { (cat, titre) ->
            groupes[cat]?.takeIf { it.isNotEmpty() }?.let { liste ->
                Libelle(titre)
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    liste.forEach { t ->
                        PuceSelection(t.libelle, f.type?.valeur == t.valeur) {
                            vm.modifier(listOf("type")) {
                                // Capacité ramenée dans les limites du nouveau type (20 pour un logement, 1 000 pour une salle)
                                // Journée laissée par défaut : celle d'une salle finit le jour même, celle d'un logement le lendemain
                                val heures = tarifs.journee.heureArrivee to tarifs.journee.heureDepart
                                val journee = when {
                                    t.estSalle && heures == ("14:00" to "12:00") -> tarifs.journee.copy(heureArrivee = "08:00", heureDepart = "22:00")
                                    !t.estSalle && heures == ("08:00" to "22:00") -> tarifs.journee.copy(heureArrivee = "14:00", heureDepart = "12:00")
                                    else -> tarifs.journee
                                }
                                copy(type = t, tarifs = tarifs.copy(journee = journee), capacite = capacite.coerceAtMost(t.capaciteMax ?: if (t.estSalle) 1000 else 20).coerceAtLeast(if (t.estSalle) 10 else 1))
                            }
                        }
                    }
                }
            }
        }
        Erreur(e["type"])
    }
    Bloc {
        ChampTexte(f.description.orEmpty(), { v -> vm.modifier(listOf("description")) { copy(description = v.take(3000)) } }, "Description (facultatif)",
            lignes = 5, indication = "Ce qui rend le logement agréable : climatisation, calme, vue, proximité…", erreur = e["description"])
    }
}

@Composable
private fun EtapeEmplacement(vm: EditionLogementViewModel) {
    val f = vm.f
    val e = vm.erreurs
    val context = LocalContext.current
    val portee = rememberCoroutineScope()
    var recherche by remember { mutableStateOf(false) }
    Bloc {
        Libelle("Ville")
        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Catalogue.configuration?.villes.orEmpty().forEach { v -> PuceSelection(v, f.ville == v) { vm.modifier(listOf("ville")) { copy(ville = v) } } }
        }
        Erreur(e["ville"])
        ChampTexte(f.quartier.orEmpty(), { v -> vm.modifier(listOf("quartier")) { copy(quartier = v.take(120)) } }, "Quartier", indication = "Ex. Fidjrossè", erreur = e["quartier"])
        ChampTexte(f.adresse.orEmpty(), { v -> vm.modifier(listOf("adresse")) { copy(adresse = v.take(255)) } }, "Adresse exacte (facultatif)",
            indication = "Rue, repère, couleur du portail…", aide = "Donnée au client seulement sur son billet, après le paiement.", erreur = e["adresse"])
    }
    val demande = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { ok ->
        if (ok) portee.launch { recherche = true; positionner(context, vm); recherche = false }
        else Messages.info("Autorisez la localisation pour placer le logement sur la carte.")
    }
    Carte(fond = if (f.latitude != null) Vivi.SuccessBg else Vivi.White) {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Outlined.MyLocation, null, tint = if (f.latitude != null) Vivi.Success else Vivi.Teal700)
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f)) {
                    Text(if (f.latitude != null) "Position enregistrée" else "Position sur la carte", style = MaterialTheme.typography.titleSmall)
                    Text(
                        if (f.latitude != null && f.longitude != null) "%.5f, %.5f".format(Fmt.locale, f.latitude, f.longitude)
                        else "Recommandée : le logement apparaît dans « Près de moi ».",
                        style = MaterialTheme.typography.bodySmall,
                    )
                }
            }
            BoutonContour(
                if (f.latitude != null) "Mettre à jour avec ma position" else "Je suis dans le logement : utiliser ma position",
                { if (Position.autorisee(context)) portee.launch { recherche = true; positionner(context, vm); recherche = false } else demande.launch(Manifest.permission.ACCESS_COARSE_LOCATION) },
                Modifier.fillMaxWidth(), enCours = recherche, icone = Icons.Outlined.MyLocation,
            )
            Erreur(e["latitude"] ?: e["longitude"])
        }
    }
}

@Composable
private fun EtapeCapacite(vm: EditionLogementViewModel) {
    val f = vm.f
    val salle = estSalle(f.type)
    Bloc {
        if (salle) {
            // Salle : seulement le nombre de personnes (jusqu'à 1 000, par dizaines)
            LigneCompteur("Places", f.capacite, 10, 1000, aide = "Nombre de places que la salle peut offrir.", pas = 10) { v -> vm.modifier { copy(capacite = v) } }
        } else {
            LigneCompteur("Voyageurs au maximum", f.capacite, 1, 20) { v -> vm.modifier { copy(capacite = v) } }
            Separateur()
            LigneCompteur("Chambres", f.chambres, 0, 20) { v -> vm.modifier { copy(chambres = v) } }
            Separateur()
            LigneCompteur("Lits", f.lits, 1, 20) { v -> vm.modifier { copy(lits = v) } }
            Separateur()
            LigneCompteur("Salles de bain", f.sallesDeBain, 0, 10) { v -> vm.modifier { copy(sallesDeBain = v) } }
        }
    }
    Bloc {
        if (!salle) {
            LigneCompteur("Logements identiques", f.unites, 1, 200, aide = "Plusieurs appartements pareils à la même adresse ? Indiquez-en le nombre.") { v -> vm.modifier { copy(unites = v) } }
            Separateur()
        }
        Libelle("Préparation entre deux clients")
        Segmente(listOf(0 to "Aucune", 30 to "30 min", 60 to "1 h", 120 to "2 h"), f.tempsPreparation, { v -> vm.modifier { copy(tempsPreparation = v) } })
        Text(if (salle) "La salle n’est pas proposée pendant ce temps après chaque événement (ménage, rangement)." else "Le logement n’est pas proposé pendant ce temps après chaque départ (ménage, linge).", style = MaterialTheme.typography.bodySmall)
    }
}

@Composable
private fun EtapeTarifs(vm: EditionLogementViewModel) {
    val f = vm.f
    val salle = estSalle(f.type)
    val e = vm.erreurs
    e["tarifs"]?.let { Bandeau(it, ton = TonPuce.Danger, icone = Icons.Outlined.ErrorOutline) }
    Bloc {
        Formule("À l’heure", Icons.Outlined.Schedule, f.tarifs.heure.actif, { vm.activer("heure", it) }) {
            ChampPrix(f.tarifs.heure.prix, "Prix par heure", e["tarifs.heure.prix"]) { p -> vm.tarif("heure") { copy(prix = p) } }
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                ChampHeure("Ouverture", f.tarifs.heure.heureOuverture, Modifier.weight(1f), e["tarifs.heure.heure_ouverture"]) { h -> vm.tarif("heure") { copy(heureOuverture = h) } }
                ChampHeure("Fermeture", f.tarifs.heure.heureFermeture, Modifier.weight(1f), e["tarifs.heure.heure_fermeture"]) { h -> vm.tarif("heure") { copy(heureFermeture = h) } }
            }
            LigneCompteur("Durée minimum", f.tarifs.heure.dureeMinimumHeures ?: 1, 1, 6, suffixe = " h") { v -> vm.tarif("heure") { copy(dureeMinimumHeures = v) } }
        }
    }
    Bloc {
        Formule("À la demi-journée", Icons.Outlined.WbTwilight, f.tarifs.demiJournee.actif, { vm.activer("demi_journee", it) }) {
            ChampPrix(f.tarifs.demiJournee.prix, "Prix du créneau", e["tarifs.demi_journee.prix"]) { p -> vm.tarif("demi_journee") { copy(prix = p) } }
            Libelle("Créneaux (4 au plus)")
            Erreur(e["creneaux"])
            f.creneaux.forEachIndexed { i, c ->
                Carte(fond = Vivi.Sand, remplissage = PaddingValues(12.dp)) {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        Row(verticalAlignment = Alignment.Top) {
                            ChampTexte(c.nom, { v -> vm.modifier(listOf("creneaux.$i.nom")) { copy(creneaux = creneaux.mapIndexed { j, x -> if (j == i) x.copy(nom = v.take(40)) else x }) } },
                                "Nom", Modifier.weight(1f), erreur = e["creneaux.$i.nom"])
                            IconButton(onClick = { vm.modifier { copy(creneaux = creneaux.filterIndexed { j, _ -> j != i }) } }, modifier = Modifier.padding(top = 22.dp)) {
                                Icon(Icons.Outlined.Delete, contentDescription = "Supprimer le créneau ${c.nom}", tint = Vivi.Danger)
                            }
                        }
                        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            ChampHeure("Début", c.heureDebut, Modifier.weight(1f), e["creneaux.$i.heure_debut"]) { h ->
                                vm.modifier { copy(creneaux = creneaux.mapIndexed { j, x -> if (j == i) x.copy(heureDebut = h) else x }) }
                            }
                            ChampHeure("Fin", c.heureFin, Modifier.weight(1f), e["creneaux.$i.heure_fin"]) { h ->
                                vm.modifier { copy(creneaux = creneaux.mapIndexed { j, x -> if (j == i) x.copy(heureFin = h) else x }) }
                            }
                        }
                    }
                }
            }
            if (f.creneaux.size < 4) BoutonTexte("Ajouter un créneau", {
                vm.modifier(listOf("creneaux")) { copy(creneaux = creneaux + FicheLogementPro.Creneau(nom = "", heureDebut = "08:00", heureFin = "13:00")) }
            }, icone = Icons.Outlined.Add)
        }
    }
    Bloc {
        Formule("À la journée", Icons.Outlined.WbSunny, f.tarifs.journee.actif, { vm.activer("journee", it) }) {
            ChampPrix(f.tarifs.journee.prix, "Prix par jour", e["tarifs.journee.prix"]) { p -> vm.tarif("journee") { copy(prix = p) } }
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                ChampHeure(if (salle) "Heure de début" else "Arrivée dès", f.tarifs.journee.heureArrivee, Modifier.weight(1f), e["tarifs.journee.heure_arrivee"]) { h -> vm.tarif("journee") { copy(heureArrivee = h) } }
                ChampHeure(if (salle) "Heure de fin" else "Départ avant", f.tarifs.journee.heureDepart, Modifier.weight(1f), e["tarifs.journee.heure_depart"]) { h -> vm.tarif("journee") { copy(heureDepart = h) } }
            }
            if (salle) {
                val (debut, fin) = f.tarifs.journee.heureArrivee to f.tarifs.journee.heureDepart
                Text(
                    if (debut != null && fin != null && fin < debut) "La journée finit à $fin, après minuit."
                    else "La journée finit le jour même. Pour finir après minuit, choisissez une heure de fin avant l’heure de début.",
                    style = MaterialTheme.typography.bodySmall,
                )
            }
        }
    }
}

@Composable
private fun EtapeEquipements(vm: EditionLogementViewModel) {
    val f = vm.f
    val config = Catalogue.configuration
    Bloc {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Libelle("Équipements")
            Spacer(Modifier.weight(1f))
            Text("${f.equipements.size} choisi${if (f.equipements.size > 1) "s" else ""}", style = MaterialTheme.typography.bodySmall)
        }
        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            config?.equipements.orEmpty().forEach { q ->
                val choisi = q.id in f.equipements
                PuceSelection(q.nom, choisi) { vm.modifier { copy(equipements = if (choisi) equipements - q.id else equipements + q.id) } }
            }
        }
    }
    if (config?.services.orEmpty().isNotEmpty()) Bloc {
        Libelle("Services à la carte proposés")
        Text("Le client les ajoute à sa réservation ; VIVI fixe leur prix.", style = MaterialTheme.typography.bodySmall)
        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            config?.services.orEmpty().forEach { s ->
                val choisi = f.services.any { it.id == s.id }
                PuceSelection("${s.nom} · ${Fmt.fcfa(s.prix)}", choisi) {
                    vm.modifier { copy(services = if (choisi) services.filter { it.id != s.id } else services + FicheLogementPro.ServiceChoisi(s.id, s.nom)) }
                }
            }
        }
    }
}

/** Gestion par le propriétaire ou confiée à VIVI ; gérant, rémunération et contrat sont fixés par l'administration (lecture seule). */
@Composable
private fun EtapeGestion(vm: EditionLogementViewModel) {
    val f = vm.f
    val choisi = f.modeGestion?.valeur?.ifBlank { null } ?: "proprietaire"
    val modes = Catalogue.configuration?.modesGestion.orEmpty()
    if (modes.isEmpty()) {
        Bandeau("Les choix de gestion ne sont pas disponibles pour le moment.", ton = TonPuce.Neutre, icone = Icons.Outlined.Info)
        return
    }
    modes.forEach { m ->
        val actif = m.valeur == choisi
        Row(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).background(if (actif) Vivi.Teal50 else Vivi.White)
                .border(if (actif) 2.dp else 1.dp, if (actif) Vivi.Teal700 else Vivi.Line, RoundedCornerShape(16.dp))
                .selectable(actif, role = Role.RadioButton) { vm.modifier(listOf("mode_gestion")) { copy(modeGestion = Enumeration(m.valeur, m.libelle)) } }
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            androidx.compose.material3.RadioButton(actif, null, colors = androidx.compose.material3.RadioButtonDefaults.colors(selectedColor = Vivi.Teal700, unselectedColor = Vivi.Muted))
            Spacer(Modifier.width(14.dp))
            Column(Modifier.weight(1f)) {
                Text(m.choix.ifBlank { m.libelle }, style = MaterialTheme.typography.titleSmall, color = Vivi.Ink)
                if (m.aide.isNotBlank()) Text(m.aide, style = MaterialTheme.typography.bodySmall)
            }
        }
    }
    Erreur(vm.erreurs["mode_gestion"])
    if (choisi == "vivi") Bloc {
        TitreSection("Gestion par VIVI")
        Column {
            Text("Gérant VIVI", style = MaterialTheme.typography.bodySmall)
            Text(
                f.gerantVivi?.nom ?: if (f.gestionDemandeeLe != null) "En attente de désignation par VIVI" else "Désigné par VIVI après l’enregistrement",
                style = MaterialTheme.typography.bodyLarge, color = Vivi.Ink,
            )
            f.gestionDemandeeLe?.let { Text("Demandée le ${Fmt.numerique(it)}", style = MaterialTheme.typography.bodySmall) }
        }
        Separateur()
        Column {
            Text("Rémunération de VIVI", style = MaterialTheme.typography.bodySmall)
            Text(f.gestionRemuneration?.libelle?.ifBlank { null } ?: "À fixer avec VIVI", style = MaterialTheme.typography.bodyLarge, color = Vivi.Ink)
        }
        f.gestionRemuneration?.contrat?.takeIf { it.isNotBlank() }?.let { contrat ->
            Separateur()
            Column {
                Text("Contrat" + (f.gestionRemuneration.contratLe?.let { " du ${Fmt.numerique(it)}" } ?: ""), style = MaterialTheme.typography.bodySmall)
                Text(contrat, style = MaterialTheme.typography.bodyLarge, color = Vivi.Ink)
            }
        }
    }
}

@Composable
private fun EtapeMarketing(vm: EditionLogementViewModel) {
    val f = vm.f
    val e = vm.erreurs
    Bloc {
        Segmente(listOf<Pair<String?, String>>(null to "Aucune", "pourcentage" to "Pourcentage", "fixe" to "Montant fixe"), f.marketingType,
            { t -> vm.modifier(listOf("marketing_type", "marketing_valeur")) { copy(marketingType = t) } })
        if (f.marketingType != null) {
            ChampTexte(
                vm.partTexte, { v -> vm.partTexte = v.filter { it.isDigit() || it == ',' || it == '.' }.take(9) },
                if (f.marketingType == "pourcentage") "Pourcentage de la location (50 % au plus)" else "Montant par réservation (FCFA)",
                clavier = KeyboardType.Decimal, erreur = e["marketing_valeur"] ?: e["marketing_type"],
            )
        }
    }
    Bandeau("La part est déduite de la location quand un client réserve par le lien d’un agent. Sans agent, elle revient à VIVI. Désignez vos agents dans Plus › Agents marketing.",
        ton = TonPuce.Teal, icone = Icons.Outlined.Info)
}

/** Dernière étape de la création : tout ce qui a été saisi, avec un lien vers chaque étape. */
@Composable
private fun EtapeRecapitulatif(vm: EditionLogementViewModel) {
    val f = vm.f
    val t = f.tarifs
    @Composable
    fun Ligne(etape: Int, titre: String, valeur: String) {
        Row(Modifier.fillMaxWidth().clickable(role = Role.Button) { vm.allerA(etape) }.padding(vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(titre, style = MaterialTheme.typography.bodySmall)
                Text(valeur, style = MaterialTheme.typography.bodyLarge, color = Vivi.Ink)
            }
            Text("Modifier", color = Vivi.Teal700, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
        }
    }
    Carte {
        Column {
            Ligne(0, "Nom et type", "${f.titre.ifBlank { "—" }} · ${f.type?.libelle.orEmpty()}")
            Separateur()
            Ligne(1, "Emplacement", listOfNotNull(f.quartier?.ifBlank { null }, f.ville).joinToString(", ") + if (f.latitude != null) " · position enregistrée" else " · sans position")
            Separateur()
            Ligne(2, "Capacité", if (estSalle(f.type)) "${f.capacite} places" else "${f.capacite} voyageurs · ${f.chambres} ch. · ${f.lits} lit${if (f.lits > 1) "s" else ""} · ${f.sallesDeBain} sdb")
            Separateur()
            Ligne(3, "Formules", listOfNotNull(
                t.heure.prix?.takeIf { t.heure.actif }?.let { "${Fmt.fcfa(it)} / h" },
                t.demiJournee.prix?.takeIf { t.demiJournee.actif }?.let { "${Fmt.fcfa(it)} / créneau" },
                t.journee.prix?.takeIf { t.journee.actif }?.let { "${Fmt.fcfa(it)} / jour" },
            ).joinToString(" · ").ifEmpty { "Aucune formule" })
            Separateur()
            Ligne(4, "Équipements et services", "${f.equipements.size} équipement${if (f.equipements.size > 1) "s" else ""} · ${f.services.size} service${if (f.services.size > 1) "s" else ""}")
            Separateur()
            Ligne(5, "Gestion", f.modeGestion?.libelle?.ifBlank { null } ?: "Je gère moi-même")
            Separateur()
            Ligne(6, "Part de l’agent", when (f.marketingType) { "pourcentage" -> "${vm.partTexte} % de la location"; "fixe" -> "${vm.partTexte} FCFA par réservation"; else -> "Aucune" })
        }
    }
    Bandeau("En créant le logement, vous certifiez l’exactitude de l’annonce. VIVI la vérifie avant sa mise en ligne si votre abonnement le permet ; sinon elle reste en brouillon.",
        ton = TonPuce.Neutre, icone = Icons.Outlined.Info)
}

private suspend fun positionner(context: Context, vm: EditionLogementViewModel) {
    if (!Position.active(context)) { Messages.info("Activez la localisation du téléphone, puis réessayez."); return }
    val p = Position.obtenir(context)
    if (p == null) Messages.erreur("Position introuvable pour l’instant. Réessayez dans quelques secondes.")
    else vm.modifier(listOf("latitude", "longitude")) { copy(latitude = p.lat, longitude = p.lng) }
}

@Composable
private fun Photos(vm: EditionLogementViewModel) {
    val context = LocalContext.current
    val photos = vm.f.photos
    var aSupprimer by remember { mutableStateOf<Long?>(null) }
    val choix = rememberLauncherForActivityResult(ActivityResultContracts.PickMultipleVisualMedia(10)) { uris -> vm.ajouterPhotos(context, uris) }

    Text("${photos.size} / 15 · 800 × 600 pixels au minimum. La première est la photo principale.", style = MaterialTheme.typography.bodySmall)
    FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        photos.forEachIndexed { i, p ->
            Box(Modifier.size(104.dp).clip(RoundedCornerShape(12.dp))) {
                PhotoLogement(p.url, "Photo ${i + 1}", Modifier.fillMaxSize())
                Row(Modifier.align(Alignment.BottomCenter).fillMaxWidth().background(Color.Black.copy(alpha = .45f)), horizontalArrangement = Arrangement.SpaceBetween) {
                    IconButton(onClick = { if (i > 0) vm.photoPrincipale(p.id) }, enabled = !vm.envoiPhotos, modifier = Modifier.size(40.dp)) {
                        Icon(if (i == 0) Icons.Outlined.Star else Icons.Outlined.StarOutline,
                            contentDescription = if (i == 0) "Photo principale" else "Choisir comme photo principale", tint = if (i == 0) Vivi.Orange400 else Vivi.White)
                    }
                    IconButton(onClick = { aSupprimer = p.id }, enabled = !vm.envoiPhotos, modifier = Modifier.size(40.dp)) {
                        Icon(Icons.Outlined.Delete, contentDescription = "Supprimer la photo ${i + 1}", tint = Vivi.White)
                    }
                }
            }
        }
        if (photos.size < 15) {
            Box(
                Modifier.size(104.dp).clip(RoundedCornerShape(12.dp)).border(1.5.dp, Vivi.Teal700, RoundedCornerShape(12.dp)).background(Vivi.Teal50)
                    .clickable(enabled = !vm.envoiPhotos, role = Role.Button) { choix.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)) },
                contentAlignment = Alignment.Center,
            ) {
                if (vm.envoiPhotos) CircularProgressIndicator(Modifier.size(28.dp), color = Vivi.Teal700, strokeWidth = 3.dp)
                else Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Outlined.AddAPhoto, null, tint = Vivi.Teal700)
                    Text("Ajouter", color = Vivi.Teal800, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
    aSupprimer?.let { photo ->
        AlertDialog(
            onDismissRequest = { aSupprimer = null },
            title = { Text("Supprimer cette photo ?") },
            confirmButton = { TextButton(onClick = { vm.supprimerPhoto(photo); aSupprimer = null }) { Text("Supprimer", color = Vivi.Danger, fontWeight = FontWeight.SemiBold) } },
            dismissButton = { TextButton(onClick = { aSupprimer = null }) { Text("Annuler", color = Vivi.Teal700) } },
            containerColor = Vivi.White,
        )
    }
}

/* ---------- Petits éléments du formulaire ---------- */

@Composable
private fun Section(titre: String, numero: Int, contenu: @Composable () -> Unit) {
    Carte {
        Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
            TitreSection(titre, surtitre = "Étape $numero")
            contenu()
        }
    }
}

@Composable
private fun Libelle(texte: String) = Text(texte, style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)

@Composable
private fun Erreur(texte: String?) {
    if (texte != null) Text(texte, style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Danger))
}

@Composable
private fun PuceSelection(texte: String, choisi: Boolean, onClick: () -> Unit) {
    FilterChip(
        selected = choisi, onClick = onClick,
        label = { Text(texte, fontWeight = FontWeight.SemiBold, color = if (choisi) Vivi.White else Vivi.Body) },
        leadingIcon = if (choisi) { { Icon(Icons.Outlined.Check, null, Modifier.size(16.dp), tint = Vivi.White) } } else null,
        shape = RoundedCornerShape(999.dp),
        modifier = Modifier.heightIn(min = 40.dp),
        colors = FilterChipDefaults.filterChipColors(selectedContainerColor = Vivi.Teal700, containerColor = Vivi.White),
        border = FilterChipDefaults.filterChipBorder(enabled = true, selected = choisi, borderColor = Vivi.LineStrong, selectedBorderColor = Vivi.Teal700),
    )
}

@Composable
private fun LigneCompteur(libelle: String, valeur: Int, min: Int, max: Int, aide: String? = null, suffixe: String = "", pas: Int = 1, onValeur: (Int) -> Unit) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            Text(libelle, style = MaterialTheme.typography.bodyLarge, color = Vivi.Ink)
            aide?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
        }
        Compteur(valeur.coerceIn(min, max), onValeur, min, max, libelle, suffixe = suffixe, compact = true, pas = pas)
    }
}

@Composable
private fun Formule(titre: String, icone: ImageVector, actif: Boolean, onActif: (Boolean) -> Unit, contenu: @Composable () -> Unit) {
    Row(
        Modifier.fillMaxWidth().heightIn(min = 52.dp).toggleable(actif, role = Role.Switch, onValueChange = onActif),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(36.dp).clip(CircleShape).background(if (actif) Vivi.Teal50 else Vivi.Segmented), contentAlignment = Alignment.Center) {
            Icon(icone, null, tint = if (actif) Vivi.Teal700 else Vivi.Muted, modifier = Modifier.size(20.dp))
        }
        Spacer(Modifier.width(12.dp))
        Text(titre, style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
        Switch(actif, null, colors = SwitchDefaults.colors(checkedTrackColor = Vivi.Teal700))
    }
    if (actif) Column(verticalArrangement = Arrangement.spacedBy(12.dp)) { contenu() }
}

@Composable
private fun ChampPrix(prix: Int?, libelle: String, erreur: String?, onPrix: (Int?) -> Unit) {
    ChampTexte(prix?.toString().orEmpty(), { v -> onPrix(v.filter(Char::isDigit).take(7).toIntOrNull()) }, "$libelle (FCFA)",
        clavier = KeyboardType.Number, indication = "Ex. 5000", erreur = erreur)
}

/** Champ d'heure : touché, il ouvre l'horloge (format 24 h). */
@Composable
private fun ChampHeure(libelle: String, valeur: String?, modifier: Modifier = Modifier, erreur: String? = null, onHeure: (String) -> Unit) {
    var ouvert by remember { mutableStateOf(false) }
    Column(modifier) {
        Text(libelle, style = MaterialTheme.typography.labelMedium, color = Vivi.Ink, modifier = Modifier.padding(bottom = 6.dp))
        Row(
            Modifier.fillMaxWidth().heightIn(min = 52.dp).clip(RoundedCornerShape(12.dp))
                .border(1.dp, if (erreur != null) Vivi.Danger else Vivi.LineStrong, RoundedCornerShape(12.dp)).background(Vivi.White)
                .clickable(role = Role.Button) { ouvert = true }.padding(horizontal = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(valeur ?: "--:--", style = MaterialTheme.typography.bodyLarge, color = Vivi.Ink, modifier = Modifier.weight(1f))
            Icon(Icons.Outlined.Schedule, null, tint = Vivi.Teal700, modifier = Modifier.size(20.dp))
        }
        Erreur(erreur)
    }
    if (ouvert) {
        val (h, m) = (valeur ?: "08:00").split(':').map { it.toIntOrNull() ?: 0 }.let { it.getOrElse(0) { 8 } to it.getOrElse(1) { 0 } }
        val etat = rememberTimePickerState(initialHour = h, initialMinute = m, is24Hour = true)
        AlertDialog(
            onDismissRequest = { ouvert = false },
            title = { Text(libelle) },
            text = { TimePicker(etat, colors = TimePickerDefaults.colors(selectorColor = Vivi.Teal700, timeSelectorSelectedContainerColor = Vivi.Teal50)) },
            confirmButton = { TextButton(onClick = { onHeure("%02d:%02d".format(etat.hour, etat.minute)); ouvert = false }) { Text("Valider", color = Vivi.Teal700, fontWeight = FontWeight.SemiBold) } },
            dismissButton = { TextButton(onClick = { ouvert = false }) { Text("Annuler", color = Vivi.Muted) } },
            containerColor = Vivi.White,
        )
    }
}

/** Types de salle connus, si la configuration n'a pas pu être lue. */
private val TypesSalle = setOf("salle_fetes", "salle_conference", "salle_reunion", "espace_plein_air", "espace_complet")

/** Salle (capacité en places, jusqu'à 1 000 ; ni chambres, ni lits, ni unités). */
private fun estSalle(type: bj.vivi.app.data.Enumeration?): Boolean {
    if (type == null) return false
    val duCatalogue = Catalogue.configuration?.typesLogement?.firstOrNull { it.valeur == type.valeur }
    return (duCatalogue?.categorie ?: type.categorie)?.let { it == "salle" } ?: (type.valeur in TypesSalle)
}
