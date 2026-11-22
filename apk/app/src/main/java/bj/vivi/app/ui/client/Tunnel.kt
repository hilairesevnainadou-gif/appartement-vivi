@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class, androidx.compose.foundation.layout.ExperimentalLayoutApi::class)

package bj.vivi.app.ui.client

import android.util.Patterns
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
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
import androidx.compose.material.icons.automirrored.outlined.OpenInNew
import androidx.compose.material.icons.outlined.Check
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.CreditCard
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.EventRepeat
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.HourglassTop
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.PhoneAndroid
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.Timer
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.layout.positionInParent
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.Catalogue
import bj.vivi.app.data.Devis
import bj.vivi.app.data.ErreurVivi
import bj.vivi.app.data.EtatPaiement
import bj.vivi.app.data.Reservation
import bj.vivi.app.data.appel
import bj.vivi.app.data.corps
import bj.vivi.app.ui.AvecEtat
import bj.vivi.app.ui.EcranViewModel
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
import bj.vivi.app.ui.composants.Chargement
import bj.vivi.app.ui.composants.SqueletteDetail
import bj.vivi.app.ui.composants.Compteur
import bj.vivi.app.ui.composants.LigneMontant
import bj.vivi.app.ui.composants.PhotoLogement
import bj.vivi.app.ui.composants.Puce
import bj.vivi.app.ui.composants.Separateur
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.donnees
import bj.vivi.app.ui.ouvrirPage
import bj.vivi.app.ui.pluriel
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.time.Duration
import java.time.OffsetDateTime

/** Adresses des pages FedaPay ouvertes pendant ce lancement : l'écran de suivi peut les rouvrir. */
object PaiementsLances {
    private val urls = mutableMapOf<Long, String>()
    fun noter(id: Long, url: String) { urls[id] = url }
    fun url(id: Long): String? = urls[id]
}

/** Étapes « Services » et « Paiement » : le créneau est retenu jusqu'à retenu_jusqua. */
class TunnelViewModel(etatNav: SavedStateHandle) : EcranViewModel<Reservation>() {
    val reference: String = checkNotNull(etatNav["ref"])
    var quantites by mutableStateOf<Map<Long, Int>>(emptyMap())
        private set
    var utiliserBonus by mutableStateOf(true)
        private set
    var devis by mutableStateOf<Devis?>(null)
        private set
    var calculEnCours by mutableStateOf(false)
        private set
    var nom by mutableStateOf("")
    /** Numéro WhatsApp où le gérant peut joindre le client, de n'importe quel pays (indicatif + numéro national). */
    var indicatif by mutableStateOf("229")
    var telephone by mutableStateOf("")
    var email by mutableStateOf("")
    var cgv by mutableStateOf(false)
    var erreurs by mutableStateOf<Map<String, String>>(emptyMap())
        private set
    private var calcul: Job? = null

    init { recharger() }

    override suspend fun charger() = appel { reservation(reference) }.map { it.data }.onSuccess { r ->
        quantites = r.servicesProposes.associate { it.id to it.quantite }.filterValues { it > 0 }
        devis = r.devis
        utiliserBonus = (r.bonus?.disponible ?: 0) > 0
        r.coordonnees?.let { c ->
            if (nom.isEmpty()) nom = c.nom.orEmpty()
            if (telephone.isEmpty()) {
                // Dernier WhatsApp donné, sinon le numéro béninois du compte
                val duCompte = bj.vivi.app.data.Reseau.session.utilisateur.value?.whatsapp
                bj.vivi.app.ui.composants.Whatsapp.decouper(c.whatsapp ?: duCompte ?: c.telephone)?.let { (i, n) -> indicatif = i; telephone = n }
            }
            if (email.isEmpty()) email = c.email.orEmpty()
        }
    }

    fun quantite(service: Long, q: Int) {
        quantites = (quantites + (service to q)).filterValues { it > 0 }
        recalculer()
    }

    fun bonus(actif: Boolean) {
        utiliserBonus = actif
        recalculer()
    }

    fun effacerErreur(champ: String) {
        if (champ in erreurs) erreurs = erreurs - champ
    }

    private fun recalculer() {
        calcul?.cancel()
        calcul = viewModelScope.launch {
            calculEnCours = true
            delay(200)
            appel { devis(reference, corps("services" to quantites.mapKeys { it.key.toString() }, "bonus" to utiliserBonus)) }
                .onSuccess { devis = it.data }
            calculEnCours = false
        }
    }

    /** Vérifie la saisie avant d'appeler le serveur : chaque champ en défaut reçoit son message. */
    fun verifier(): Boolean {
        val e = buildMap {
            if (nom.trim().length < 3) put("nom", "Indiquez votre nom et votre prénom.")
            if (!bj.vivi.app.ui.composants.Whatsapp.valide(indicatif, telephone)) put(
                "whatsapp",
                if (indicatif == "229") "Indiquez un numéro béninois à 10 chiffres commençant par 01 (ex. 01 97 00 00 00)."
                else "Indiquez votre numéro WhatsApp complet, sans l’indicatif du pays.",
            )
            if (email.isNotBlank() && !Patterns.EMAIL_ADDRESS.matcher(email.trim()).matches()) put("email", "Cette adresse email n’est pas valide.")
            if (!cgv) put("cgv", "Cochez la case pour accepter les conditions avant de payer.")
        }
        erreurs = e
        if (e.isNotEmpty()) Messages.erreur("Vérifiez les informations signalées en rouge.")
        return e.isEmpty()
    }

    fun payer(surPaiement: (url: String, paiement: Long) -> Unit) {
        if (!verifier()) return
        action(surErreur = { e -> erreurs = e.champs.ifEmpty { mapOf("paiement" to e.message) } }) {
            appel {
                payer(reference, corps(
                    "nom" to nom.trim(), "whatsapp" to bj.vivi.app.ui.composants.Whatsapp.international(indicatif, telephone), "email" to email.trim().ifEmpty { null }, "cgv" to cgv,
                    "services" to quantites.mapKeys { it.key.toString() }, "utiliser_bonus" to utiliserBonus,
                ))
            }.map {
                PaiementsLances.noter(it.data.paiement.id, it.data.urlPaiement)
                surPaiement(it.data.urlPaiement, it.data.paiement.id)
            }
        }
    }
}

@Composable
fun EcranTunnel(onRetour: () -> Unit, onPaiement: (Long) -> Unit, onBillet: (String) -> Unit, onAide: (String) -> Unit = {}, vm: TunnelViewModel = viewModel()) {
    val context = LocalContext.current
    val r = vm.etat.donnees
    LaunchedEffect(r?.statut?.valeur) { if (r != null && r.statut.valeur != "en_attente_paiement") onBillet(r.reference) }
    val defilement = rememberScrollState()
    val portee = rememberCoroutineScope()
    var hautCoordonnees by remember { mutableFloatStateOf(0f) }

    Scaffold(
        containerColor = Vivi.Sand,
        topBar = { BarreTitre("Finaliser la réservation", onRetour) },
        bottomBar = {
            if (r != null) BarreAction(Modifier.navigationBarsPadding().imePadding()) {
                Column(Modifier.weight(1f)) {
                    Text("Total à payer", style = MaterialTheme.typography.bodySmall)
                    Text(Fmt.fcfa(vm.devis?.aPayer), style = MaterialTheme.typography.titleLarge, maxLines = 1)
                }
                BoutonPrincipal(
                    "Payer", {
                        if (!vm.verifier()) {
                            // Remonte jusqu'au premier champ à corriger
                            val cible = if (vm.erreurs.keys.any { it != "cgv" }) hautCoordonnees.toInt() else defilement.maxValue
                            portee.launch { defilement.animateScrollTo(cible) }
                        } else {
                            vm.payer { _, id -> onPaiement(id) }   // page FedaPay ouverte dans l'application
                        }
                    },
                    actif = r.actions.payer && !vm.calculEnCours,
                    enCours = vm.enCours, icone = Icons.Outlined.Lock,
                )
            }
        },
    ) { marges ->
        AvecEtat(vm, Modifier.padding(marges), actualisable = false, squelette = { SqueletteDetail(it) }) { res ->
            Contenu(vm, res, defilement, onAide) { hautCoordonnees = it }
        }
    }
}

@Composable
private fun Contenu(vm: TunnelViewModel, r: Reservation, defilement: androidx.compose.foundation.ScrollState, onAide: (String) -> Unit, surPositionCoordonnees: (Float) -> Unit) {
    val context = LocalContext.current
    var etape = 0
    Column(Modifier.fillMaxSize().verticalScroll(defilement).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        Etapes(2)
        CompteARebours(r.retenuJusqua)

        // Récapitulatif du séjour
        Carte {
            Row(verticalAlignment = Alignment.CenterVertically) {
                PhotoLogement(r.logement?.photo, null, Modifier.size(72.dp).clip(RoundedCornerShape(12.dp)))
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Text(r.logement?.titre.orEmpty(), style = MaterialTheme.typography.titleMedium, maxLines = 2)
                    Text(Fmt.jourLong(r.debut), style = MaterialTheme.typography.bodyMedium, color = Vivi.Ink)
                    Text(r.avecPersonnes(r.creneau), style = MaterialTheme.typography.bodySmall)
                }
            }
        }

        // Services à la carte
        if (r.servicesProposes.isNotEmpty()) {
            etape++
            Carte {
                Column {
                    TitreSection("Services à la carte", surtitre = "Étape $etape · facultatif")
                    Spacer(Modifier.height(4.dp))
                    r.servicesProposes.forEachIndexed { i, s ->
                        if (i > 0) Separateur()
                        Row(Modifier.fillMaxWidth().padding(vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text(s.nom, style = MaterialTheme.typography.titleSmall)
                                Text(Fmt.fcfa(s.prix) + (s.unite?.let { " / $it" } ?: ""), style = MaterialTheme.typography.bodySmall)
                            }
                            Compteur(vm.quantites[s.id] ?: 0, { vm.quantite(s.id, it) }, 0, 20, s.nom, compact = true)
                        }
                    }
                }
            }
        }

        // Coordonnées
        etape++
        Carte(Modifier.onGloballyPositioned { surPositionCoordonnees(it.positionInParent().y) }) {
            Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                TitreSection("Vos coordonnées", surtitre = "Étape $etape")
                ChampTexte(vm.nom, { vm.nom = it; vm.effacerErreur("nom") }, "Nom et prénom", erreur = vm.erreurs["nom"])
                bj.vivi.app.ui.composants.ChampWhatsapp(
                    vm.indicatif, { vm.indicatif = it; vm.effacerErreur("whatsapp") },
                    vm.telephone, { vm.telephone = it; vm.effacerErreur("whatsapp"); vm.effacerErreur("telephone") },
                    aide = "Le gérant du logement vous contactera sur WhatsApp. Choisissez votre pays si vous n’êtes pas au Bénin.",
                    erreur = vm.erreurs["whatsapp"] ?: vm.erreurs["telephone"],
                )
                ChampTexte(vm.email, { vm.email = it; vm.effacerErreur("email") }, "Email (facultatif)", clavier = KeyboardType.Email,
                    aide = "Pour recevoir la confirmation et le reçu.", erreur = vm.erreurs["email"])
            }
        }

        // Prix détaillé
        vm.devis?.let { d ->
            Carte {
                Column {
                    TitreSection("Détail du prix")
                    Spacer(Modifier.height(8.dp))
                    LigneMontant("Location", Fmt.fcfa(d.location))
                    d.lignesServices.forEach { LigneMontant("${it.nom}${if (it.quantite > 1) " × ${it.quantite}" else ""}", Fmt.fcfa(it.montant)) }
                    LigneMontant("Commission VIVI (${Fmt.taux(d.tauxCommission)} %)", Fmt.fcfa(d.commission), aide = "Jamais remboursée en cas d’annulation")
                    if ((r.bonus?.disponible ?: 0) > 0) {
                        Separateur(Modifier.padding(vertical = 6.dp))
                        Row(
                            Modifier.fillMaxWidth().heightIn(min = 48.dp).toggleable(vm.utiliserBonus, role = Role.Switch, onValueChange = vm::bonus),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Column(Modifier.weight(1f)) {
                                Text("Utiliser mon bonus", style = MaterialTheme.typography.titleSmall)
                                Text("${Fmt.fcfa(r.bonus?.disponible)} disponibles · ${r.bonus?.plafondPourcentage} % du total au plus", style = MaterialTheme.typography.bodySmall)
                            }
                            Switch(vm.utiliserBonus, null, colors = SwitchDefaults.colors(checkedTrackColor = Vivi.Teal700))
                        }
                        if (d.bonus > 0) LigneMontant("Bonus", "− " + Fmt.fcfa(d.bonus), couleur = Vivi.Success)
                    }
                    if (d.remiseAgent > 0) LigneMontant("Remise agent", "− " + Fmt.fcfa(d.remiseAgent), couleur = Vivi.Success,
                        aide = "Vous promouvez ce logement : votre part vous est déduite.")
                    Separateur(Modifier.padding(vertical = 6.dp))
                    LigneMontant("Total à payer", Fmt.fcfa(d.aPayer), fort = true)
                }
            }
        }

        // Moyens de paiement
        Carte {
            Column {
                TitreSection("Moyen de paiement", surtitre = "Étape ${etape + 1}")
                Spacer(Modifier.height(10.dp))
                FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Puce("MTN MoMo", TonPuce.Ligne, Icons.Outlined.PhoneAndroid)
                    Puce("Moov Money", TonPuce.Ligne, Icons.Outlined.PhoneAndroid)
                    Puce("Celtiis Cash", TonPuce.Ligne, Icons.Outlined.PhoneAndroid)
                    Puce("Carte bancaire", TonPuce.Ligne, Icons.Outlined.CreditCard)
                }
                Spacer(Modifier.height(12.dp))
                Row(verticalAlignment = Alignment.Top) {
                    Icon(Icons.Outlined.Lock, null, tint = Vivi.Teal700, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(8.dp))
                    Text(
                        "Vous choisirez votre moyen sur la page sécurisée FedaPay. Avec Mobile Money, validez ensuite la demande reçue sur votre téléphone.",
                        style = MaterialTheme.typography.bodySmall,
                    )
                }
            }
        }

        // Conditions
        Carte(fond = if (vm.erreurs["cgv"] != null) Vivi.DangerBg else Vivi.White) {
            Column {
                Row(
                    Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp))
                        .toggleable(vm.cgv, role = Role.Checkbox) { vm.cgv = it; vm.effacerErreur("cgv") },
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Checkbox(vm.cgv, null, colors = CheckboxDefaults.colors(checkedColor = Vivi.Teal700, uncheckedColor = if (vm.erreurs["cgv"] != null) Vivi.Danger else Vivi.LineStrong))
                    Spacer(Modifier.width(10.dp))
                    Text("J’accepte les conditions générales et les conditions d’annulation et de report.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Ink, modifier = Modifier.weight(1f))
                }
                vm.erreurs["cgv"]?.let { Text(it, style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Danger), modifier = Modifier.padding(top = 6.dp)) }
                FlowRow {
                    BoutonTexte("Lire les conditions", { onAide(bj.vivi.app.ui.compte.OngletAide.CONDITIONS) }, icone = Icons.Outlined.Description)
                    BoutonTexte("Annulation et report", { onAide(bj.vivi.app.ui.compte.OngletAide.ANNULATION) }, icone = Icons.Outlined.EventRepeat)
                }
            }
        }
        vm.erreurs["paiement"]?.let { Bandeau(it, ton = TonPuce.Danger, icone = Icons.Outlined.ErrorOutline) }
        Spacer(Modifier.height(8.dp))
    }
}

/** Fil d'étapes « Réservation · Paiement · Billet » (1 à 3). */
@Composable
fun Etapes(actuelle: Int, modifier: Modifier = Modifier) {
    val noms = listOf("Réservation", "Paiement", "Billet")
    Row(modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
        noms.forEachIndexed { i, nom ->
            val n = i + 1
            val fait = n < actuelle
            val courante = n == actuelle
            if (i > 0) Box(Modifier.weight(1f).padding(top = 13.dp, start = 4.dp, end = 4.dp).height(2.dp).background(if (n <= actuelle) Vivi.Teal700 else Vivi.Line))
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Box(
                    Modifier.size(28.dp).clip(CircleShape).background(if (fait || courante) Vivi.Teal700 else Vivi.Segmented),
                    contentAlignment = Alignment.Center,
                ) {
                    if (fait) Icon(Icons.Outlined.Check, null, tint = Vivi.White, modifier = Modifier.size(16.dp))
                    else Text("$n", color = if (courante) Vivi.White else Vivi.Muted, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                }
                Spacer(Modifier.height(4.dp))
                Text(nom, fontSize = 12.sp, color = if (courante) Vivi.Teal800 else Vivi.Muted, fontWeight = if (courante) FontWeight.SemiBold else FontWeight.Normal)
            }
        }
    }
}

/** « Créneau retenu pour vous encore 12:34 » (le serveur libère le créneau à l'expiration). */
@Composable
private fun CompteARebours(jusqua: String?) {
    val fin = Fmt.dateHeure(jusqua) ?: return
    var reste by remember { mutableLongStateOf(Duration.between(OffsetDateTime.now(), fin).seconds) }
    LaunchedEffect(fin) {
        while (reste > 0) {
            delay(1000)
            reste = Duration.between(OffsetDateTime.now(), fin).seconds
        }
    }
    if (reste > 0) {
        Bandeau("Créneau retenu pour vous encore %d:%02d. Payez avant la fin du délai pour le garder.".format(reste / 60, reste % 60), ton = TonPuce.Orange, icone = Icons.Outlined.Timer)
    } else {
        Bandeau("Le délai est dépassé : le créneau a été libéré. Revenez à la fiche du logement pour réserver de nouveau.", ton = TonPuce.Danger, icone = Icons.Outlined.Info)
    }
}

/* ======================================================================
 * Suivi d'un paiement (réservation ou abonnement) : statut relu chez le serveur.
 * Ouvert juste après le lancement du paiement, ou par le lien vivi://paiement/{id}.
 * ====================================================================== */

class PaiementViewModel(etatNav: SavedStateHandle) : ViewModel() {
    val id: Long = checkNotNull(etatNav.get<String>("id")).toLong()
    var etat by mutableStateOf<EtatPaiement?>(null)
        private set
    var reservation by mutableStateOf<Reservation?>(null)
        private set
    var erreur by mutableStateOf<ErreurVivi?>(null)
        private set
    var abandon by mutableStateOf(false)
        private set
    private var suivi: Job? = null

    /** Relit le statut toutes les 3 s pendant deux minutes environ (le webhook FedaPay confirme de son côté). */
    fun suivre() {
        if (etat?.termine == true) return
        suivi?.cancel()
        abandon = false
        suivi = viewModelScope.launch {
            repeat(40) {
                appel { paiement(id) }
                    .onSuccess { etat = it.data; erreur = null; chargerReservation(it.data) }
                    .onFailure { erreur = it as ErreurVivi }
                if (etat?.termine == true) return@launch
                delay(3000)
            }
            abandon = true
        }
    }

    private fun chargerReservation(e: EtatPaiement) {
        val ref = e.objet?.reference ?: return
        if (e.objet.type != "reservation" || reservation != null) return
        viewModelScope.launch { appel { reservation(ref) }.onSuccess { reservation = it.data } }
    }
}

@Composable
fun EcranPaiement(onBillet: (String) -> Unit, onAbonnement: () -> Unit, onReessayer: (String?) -> Unit, onFermer: () -> Unit, onReprendre: (Long) -> Unit = {}, vm: PaiementViewModel = viewModel()) {
    val context = LocalContext.current
    // Reprise du suivi à chaque retour sur l'application (fermeture du navigateur de paiement)
    LifecycleEventEffect(Lifecycle.Event.ON_RESUME) { vm.suivre() }
    val e = vm.etat
    val pourReservation = e?.objet?.type != "abonnement"
    val urlPaiement = PaiementsLances.url(vm.id)
    val ouvrirSuite: () -> Unit = { if (e?.objet?.type == "reservation") e.objet.reference?.let(onBillet) else onAbonnement() }
    LaunchedEffect(e?.termine, e?.reussi) {
        if (e != null && e.reussi) {
            delay(1500)
            ouvrirSuite()
        }
    }

    Scaffold(
        containerColor = Vivi.Sand,
        topBar = { BarreTitre("Paiement", onFermer) },
        bottomBar = {
            if (e != null) BarreAction(Modifier.navigationBarsPadding()) {
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    when {
                        e.reussi -> BoutonPrincipal(if (pourReservation) "Voir mon billet" else "Voir mon abonnement", ouvrirSuite, Modifier.fillMaxWidth())
                        e.termine -> {
                            BoutonPrincipal("Réessayer le paiement", { onReessayer(e.objet?.reference) }, Modifier.fillMaxWidth(), icone = Icons.Outlined.Refresh)
                            BoutonTexte("Fermer", onFermer, Modifier.fillMaxWidth())
                        }
                        else -> {
                            if (urlPaiement != null) {
                                BoutonPrincipal("Reprendre le paiement", { onReprendre(vm.id) }, Modifier.fillMaxWidth(), icone = Icons.Outlined.Lock)
                            }
                            BoutonContour("J’ai payé, vérifier maintenant", vm::suivre, Modifier.fillMaxWidth(), enCours = !vm.abandon && urlPaiement == null)
                        }
                    }
                }
            }
        },
    ) { marges ->
        if (e == null && vm.erreur == null) {
            SqueletteDetail(Modifier.padding(marges))
            return@Scaffold
        }
        Column(
            Modifier.padding(marges).fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            if (pourReservation) Etapes(if (e?.reussi == true) 3 else 2)

            // ---------- Statut ----------
            Carte {
                Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
                    val (icone, fond, teinte) = when {
                        e?.reussi == true -> Triple(Icons.Outlined.CheckCircle, Vivi.SuccessBg, Vivi.Success)
                        e?.termine == true -> Triple(Icons.Outlined.ErrorOutline, Vivi.DangerBg, Vivi.Danger)
                        else -> Triple(Icons.Outlined.HourglassTop, Vivi.Teal50, Vivi.Teal700)
                    }
                    Spacer(Modifier.height(8.dp))
                    Box(Modifier.size(80.dp).clip(CircleShape).background(fond), contentAlignment = Alignment.Center) {
                        if (e?.termine != true && !vm.abandon) CircularProgressIndicator(Modifier.size(80.dp), color = Vivi.Teal700, strokeWidth = 3.dp)
                        Icon(icone, null, tint = teinte, modifier = Modifier.size(36.dp))
                    }
                    Spacer(Modifier.height(16.dp))
                    Text(
                        when {
                            e?.reussi == true -> "Paiement reçu, merci !"
                            e?.termine == true -> "Le paiement n’a pas abouti"
                            vm.abandon -> "Paiement en cours de confirmation"
                            else -> "En attente de votre paiement"
                        },
                        style = MaterialTheme.typography.titleLarge, textAlign = TextAlign.Center,
                    )
                    Spacer(Modifier.height(6.dp))
                    Text(
                        when {
                            e?.reussi == true -> "Nous ouvrons votre ${if (pourReservation) "billet" else "abonnement"}…"
                            e?.termine == true -> "Aucun montant n’a été débité. Vous pouvez réessayer avec le même moyen ou un autre."
                            vm.abandon -> "FedaPay n’a pas encore confirmé. Si vous avez validé sur votre téléphone, la confirmation arrive dans quelques minutes : vous serez prévenu."
                            else -> "Cette page se met à jour toute seule dès que FedaPay confirme."
                        },
                        style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted, textAlign = TextAlign.Center,
                    )
                    Spacer(Modifier.height(8.dp))
                }
            }

            // ---------- Récapitulatif ----------
            Carte {
                Column {
                    vm.reservation?.let { r ->
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            PhotoLogement(r.logement?.photo, null, Modifier.size(64.dp).clip(RoundedCornerShape(12.dp)))
                            Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) {
                                Text(r.logement?.titre.orEmpty(), style = MaterialTheme.typography.titleSmall, maxLines = 2)
                                Text(Fmt.jourLong(r.debut), style = MaterialTheme.typography.bodyMedium, color = Vivi.Ink)
                                Text(r.avecPersonnes(r.creneau), style = MaterialTheme.typography.bodySmall)
                            }
                        }
                        Separateur(Modifier.padding(vertical = 10.dp))
                    }
                    e?.let { p ->
                        LigneMontant("Montant", Fmt.fcfa(p.montant), fort = true)
                        p.moyen?.let { LigneMontant("Moyen de paiement", it) }
                        p.objet?.reference?.let { LigneMontant("Réservation", it) }
                        LigneMontant("Statut", p.statut.libelle.ifBlank { if (p.termine) "Terminé" else "En attente" },
                            couleur = when { p.reussi -> Vivi.Success; p.termine -> Vivi.Danger; else -> Vivi.OrangeInk })
                    }
                    LigneMontant("Paiement n°", vm.id.toString())
                }
            }

            // ---------- Mode d'emploi pendant l'attente ----------
            if (e != null && !e.termine) {
                Carte {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        TitreSection("Comment finaliser ?")
                        PasAPas(1, Icons.AutoMirrored.Outlined.OpenInNew, "Sur la page sécurisée FedaPay, choisissez MTN MoMo, Moov Money, Celtiis Cash ou la carte bancaire.")
                        PasAPas(2, Icons.Outlined.PhoneAndroid, "Avec Mobile Money, validez la demande qui s’affiche sur votre téléphone en tapant votre code secret.")
                        PasAPas(3, Icons.Outlined.CheckCircle, "Revenez sur VIVI : votre billet s’ouvre tout seul dès la confirmation.")
                    }
                }
            }

            vm.erreur?.let { Bandeau(it.message, ton = TonPuce.Orange, icone = Icons.Outlined.Info) }
            Bandeau("Paiement sécurisé par FedaPay. VIVI ne voit jamais votre code secret ni les numéros de votre carte.", ton = TonPuce.Teal, icone = Icons.Outlined.Lock)
        }
    }
}

@Composable
private fun PasAPas(numero: Int, icone: ImageVector, texte: String) {
    Row(verticalAlignment = Alignment.Top) {
        Box(Modifier.size(28.dp).clip(CircleShape).background(Vivi.Teal50), contentAlignment = Alignment.Center) {
            Text("$numero", color = Vivi.Teal800, fontWeight = FontWeight.Bold, fontSize = 13.sp)
        }
        Spacer(Modifier.width(12.dp))
        Text(texte, style = MaterialTheme.typography.bodyMedium, color = Vivi.Ink, modifier = Modifier.weight(1f).padding(top = 4.dp))
        Spacer(Modifier.width(8.dp))
        Icon(icone, null, tint = Vivi.Teal700, modifier = Modifier.size(20.dp).padding(top = 4.dp))
    }
}
