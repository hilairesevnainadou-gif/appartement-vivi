@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)

package bj.vivi.app.ui.compte

import androidx.compose.foundation.selection.toggleable
import androidx.compose.material.icons.outlined.AccountBalance
import androidx.compose.material.icons.outlined.Fingerprint
import bj.vivi.app.ui.donnees
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.HelpOutline
import androidx.compose.material.icons.automirrored.outlined.KeyboardArrowRight
import androidx.compose.material.icons.automirrored.outlined.Logout
import androidx.compose.material.icons.outlined.CardGiftcard
import androidx.compose.material.icons.outlined.ConfirmationNumber
import androidx.compose.material.icons.automirrored.outlined.Chat
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.DeleteOutline
import androidx.compose.material.icons.outlined.DoneAll
import androidx.compose.material.icons.outlined.PrivacyTip
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.Email
import androidx.compose.material.icons.automirrored.outlined.Login
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.NotificationsNone
import androidx.compose.material.icons.outlined.Phone
import androidx.compose.material.icons.outlined.Share
import androidx.compose.material.icons.outlined.Verified
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.BuildConfig
import bj.vivi.app.data.Catalogue
import bj.vivi.app.data.Notification
import bj.vivi.app.data.Parrainage
import bj.vivi.app.data.Profil
import bj.vivi.app.data.Reseau
import bj.vivi.app.data.Utilisateur
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
import bj.vivi.app.ui.composants.Marque
import bj.vivi.app.ui.composants.Puce
import bj.vivi.app.ui.composants.Separateur
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.ouvrirLien
import bj.vivi.app.ui.ouvrirPage
import bj.vivi.app.ui.partager
import bj.vivi.app.ui.pluriel
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.launch

/* ======================================================================
 * Compte (tous les rôles) : profil, coordonnées confirmées par code, aide, déconnexion
 * ====================================================================== */

class ProfilViewModel : EcranViewModel<Profil>() {
    var codeEnvoye by mutableStateOf<String?>(null)   // champ en cours de changement : email ou telephone
        private set
    var erreur by mutableStateOf<String?>(null)
        private set
    var codeDev by mutableStateOf<String?>(null)
        private set

    init { if (Reseau.session.jeton != null) recharger() }

    override suspend fun charger() = appel { profil() }.onSuccess { Reseau.session.mettreAJour(it.data) }

    private fun maj(u: Utilisateur) {
        etat.donnees?.let { remplacer(it.copy(data = u)) }
        Reseau.session.mettreAJour(u)
    }

    fun nom(nouveau: String, fait: () -> Unit) = action(surErreur = { erreur = it.message }) {
        appel { changerNom(corps("nom" to nouveau.trim())) }.map { maj(it.data); Messages.afficher("Nom mis à jour."); fait() }
    }

    fun demander(champ: String, valeur: String) = action(surErreur = { erreur = it.message }) {
        erreur = null
        appel { demanderChangement(corps("champ" to champ, "valeur" to valeur.trim())) }.map {
            codeEnvoye = champ
            codeDev = it.codeDev
            Messages.afficher(it.message ?: "Code envoyé.")
        }
    }

    fun confirmer(code: String, fait: () -> Unit) = action(surErreur = { erreur = it.message }) {
        appel { confirmerChangement(corps("code" to code)) }.map {
            maj(it.data)
            codeEnvoye = null
            Messages.afficher("Coordonnées mises à jour.")
            fait()
        }
    }

    fun oublierErreur() { erreur = null; codeEnvoye = null; codeDev = null }

    /** Offres de VIVI (promotions, nouveautés) : oui / non. Les notifications de réservation restent. */
    fun offres(recevoir: Boolean) = action {
        appel { preferences(corps("recevoir_offres" to recevoir)) }.map {
            maj(it.data)
            Messages.afficher(if (recevoir) "Vous recevrez les offres de VIVI." else "Vous ne recevrez plus les offres de VIVI.")
        }
    }

    fun deconnecter(context: android.content.Context, partout: Boolean = false) {
        viewModelScope.launch {
            // Ce téléphone ne reçoit plus les push de ce compte
            val jetonPush = bj.vivi.app.data.Push.jeton(context)
            appel { if (partout) deconnexionPartout() else deconnexion(corps("jeton_push" to jetonPush)) }
            bj.vivi.app.data.Push.oublier(context)
            Reseau.session.fermer()
            Messages.afficher(if (partout) "Vous êtes déconnecté de tous vos appareils." else "Vous êtes déconnecté.")
        }
    }
}

@Composable
fun EcranCompte(
    onConnexion: (String) -> Unit,
    onNotifications: () -> Unit,
    onParrainage: () -> Unit,
    onAide: (String) -> Unit = {},
    onCompteRetrait: () -> Unit = {},
    onReservations: () -> Unit = {},
    onSuppression: () -> Unit = {},
    vm: ProfilViewModel = viewModel(),
) {
    val utilisateur by Reseau.session.utilisateur.collectAsState()
    val context = LocalContext.current
    var edition by remember { mutableStateOf<String?>(null) }   // "nom", "email", "telephone"
    var confirmerSortie by remember { mutableStateOf(false) }

    Scaffold(containerColor = Vivi.Sand, topBar = { BarreTitre("Mon compte") }) { marges ->
        Column(Modifier.padding(marges).fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
            val u = utilisateur
            if (u == null) {
                Carte {
                    Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.fillMaxWidth()) {
                        Marque()
                        Spacer(Modifier.height(16.dp))
                        Text("Connectez-vous pour réserver, retrouver vos billets et gérer vos séjours.", style = MaterialTheme.typography.bodyLarge)
                        Spacer(Modifier.height(16.dp))
                        BoutonPrincipal("Me connecter", { onConnexion("connexion") }, Modifier.fillMaxWidth(), icone = Icons.AutoMirrored.Outlined.Login)
                        Spacer(Modifier.height(8.dp))
                        BoutonContour("Créer un compte", { onConnexion("inscription") }, Modifier.fillMaxWidth())
                    }
                }
            } else {
                // Même découpage que la page web « Mon profil » (resources/views/espace/profil.blade.php)
                val canaux = Catalogue.configuration?.connexion
                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(top = 4.dp)) {
                    bj.vivi.app.ui.composants.AvatarProfil(true, u.nom, onClick = null, taille = 64.dp, contour = Vivi.White)
                    Spacer(Modifier.width(14.dp))
                    Column(Modifier.weight(1f)) {
                        Text(u.nom, style = MaterialTheme.typography.headlineSmall)
                        val reservations = vm.etat.donnees?.stats?.reservations ?: 0
                        Text(u.libelleRole + (Fmt.membreDepuis(u.membreDepuis)?.let { " · membre depuis $it" } ?: "") +
                            (if (reservations > 0) " · ${pluriel(reservations, "réservation")}" else ""), style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)
                    }
                }

                // Identité
                Carte {
                    Column {
                        TitreSection("Identité")
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text("Nom et prénom", style = MaterialTheme.typography.labelMedium, color = Vivi.Muted)
                                Text(u.nom, style = MaterialTheme.typography.titleMedium)
                            }
                            BoutonContour("Modifier", { edition = "nom" }, icone = Icons.Outlined.Edit)
                        }
                        Spacer(Modifier.height(8.dp))
                        Text("Il figure sur vos réservations et vos reçus.", style = MaterialTheme.typography.bodySmall)
                    }
                }

                // Adresse email, Téléphone
                CarteCoordonnee(
                    Icons.Outlined.Email, "Adresse email", u.email, u.emailVerifie, canaux?.email != false, "email",
                    "Pour recevoir confirmations, adresse du logement, itinéraire et reçus par email, et vous connecter avec.",
                ) { edition = "email" }
                CarteCoordonnee(
                    Icons.Outlined.Phone, "Téléphone", u.telephone?.let(Fmt::telephone), u.telephoneVerifie, canaux?.sms == true, "telephone",
                    "Pour recevoir votre QR Code et vos rappels par SMS, et vous connecter avec.",
                ) { edition = "telephone" }

                // Mon compte : raccourcis
                Carte(remplissage = PaddingValues(vertical = 4.dp)) {
                    Column {
                        Text("Mon compte", style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(start = 16.dp, top = 12.dp, bottom = 4.dp))
                        LigneCompte(Icons.Outlined.ConfirmationNumber, "Mes réservations", null, null, onReservations)
                        Separateur(Modifier.padding(start = 56.dp))
                        LigneCompte(Icons.Outlined.Notifications, "Notifications", null, null, onNotifications)
                        if (Catalogue.configuration?.parrainage?.actif == true && !u.estPro) {
                            Separateur(Modifier.padding(start = 56.dp))
                            LigneCompte(Icons.Outlined.CardGiftcard, "Parrainage et bonus", null, null, onParrainage)
                        }
                        if (u.estProprietaire || u.estAgent) {
                            Separateur(Modifier.padding(start = 56.dp))
                            LigneCompte(Icons.Outlined.AccountBalance, "Compte de retrait", "Où recevoir vos retraits", null, onCompteRetrait)
                        }
                        Separateur(Modifier.padding(start = 56.dp))
                        LigneCompte(Icons.AutoMirrored.Outlined.HelpOutline, "Annulation et report", null, null) { onAide(OngletAide.ANNULATION) }
                    }
                }
                CarteOffres(u.recevoirOffres, vm.enCours) { vm.offres(it) }
                CarteSecurite()

                // Mes données
                Carte {
                    Column {
                        TitreSection("Mes données")
                        Text(
                            "VIVI utilise votre nom, votre téléphone et votre email seulement pour vos réservations, vos paiements et le service client. Ils ne sont ni vendus ni cédés.",
                            style = MaterialTheme.typography.bodySmall,
                        )
                        Spacer(Modifier.height(6.dp))
                        Text(
                            "Pour consulter ou corriger vos données, contactez le service client.",
                            style = MaterialTheme.typography.bodySmall,
                        )
                        Spacer(Modifier.height(4.dp))
                        FlowRow {
                            TextButton(onClick = { onAide(OngletAide.CONDITIONS) }) { Text("Conditions générales", color = Vivi.Teal700) }
                            Catalogue.configuration?.pages?.confidentialite?.let { lien ->
                                TextButton(onClick = { ouvrirPage(context, lien) }) { Text("Confidentialité", color = Vivi.Teal700) }
                            }
                            Catalogue.configuration?.contact?.whatsapp?.let { lien ->
                                TextButton(onClick = { ouvrirLien(context, lien) }) { Text("WhatsApp", color = Vivi.Teal700) }
                            }
                        }
                        Separateur(Modifier.padding(vertical = 4.dp))
                        BoutonTexte("Supprimer mon compte", onSuppression, icone = Icons.Outlined.DeleteOutline, couleur = Vivi.Danger)
                    }
                }
                BoutonContour("Me déconnecter", { confirmerSortie = true }, Modifier.fillMaxWidth(), icone = Icons.AutoMirrored.Outlined.Logout, danger = true)
            }

            if (u == null) {
                Carte(remplissage = PaddingValues(vertical = 4.dp)) {
                    Column {
                        Catalogue.configuration?.contact?.whatsapp?.let { lien ->
                            LigneCompte(Icons.AutoMirrored.Outlined.Chat, "Contacter VIVI sur WhatsApp", null, null) { ouvrirLien(context, lien) }
                            Separateur(Modifier.padding(start = 56.dp))
                        }
                        LigneCompte(Icons.AutoMirrored.Outlined.HelpOutline, "Annulation et report", null, null) { onAide(OngletAide.ANNULATION) }
                        Separateur(Modifier.padding(start = 56.dp))
                        LigneCompte(Icons.Outlined.Description, "Conditions générales", null, null) { onAide(OngletAide.CONDITIONS) }
                        Catalogue.configuration?.pages?.confidentialite?.let { lien ->
                            Separateur(Modifier.padding(start = 56.dp))
                            LigneCompte(Icons.Outlined.PrivacyTip, "Politique de confidentialité", null, null) { ouvrirPage(context, lien) }
                        }
                    }
                }
            }
            // Choix de publicité (Google AdMob) : proposé seulement là où la loi l'exige
            if (bj.vivi.app.data.RegieAdmob.choixAProposer) Carte(remplissage = PaddingValues(vertical = 4.dp)) {
                LigneCompte(Icons.Outlined.PrivacyTip, "Confidentialité des annonces", "Modifier votre choix", null) {
                    (context as? android.app.Activity)?.let { bj.vivi.app.data.RegieAdmob.ouvrirChoix(it, Messages::erreur) }
                }
            }
            Text("VIVI ${BuildConfig.VERSION_NAME} · Nova Tech Bénin", style = MaterialTheme.typography.bodySmall, modifier = Modifier.align(Alignment.CenterHorizontally))
        }
    }

    edition?.let { champ -> utilisateur?.let { u -> FeuilleEdition(champ, u, vm) { edition = null; vm.oublierErreur() } } }
    if (confirmerSortie) {
        AlertDialog(
            onDismissRequest = { confirmerSortie = false },
            title = { Text("Se déconnecter ?") },
            text = { Text("Vous pourrez vous reconnecter à tout moment avec un code reçu par email ou SMS" + if (Catalogue.configuration?.connexion?.google == true) ", ou avec Google." else ".") },
            confirmButton = { TextButton(onClick = { confirmerSortie = false; vm.deconnecter(context) }) { Text("Me déconnecter", color = Vivi.Danger, fontWeight = FontWeight.SemiBold) } },
            dismissButton = {
                Row {
                    TextButton(onClick = { confirmerSortie = false; vm.deconnecter(context, partout = true) }) { Text("Tous mes appareils", color = Vivi.Muted) }
                    TextButton(onClick = { confirmerSortie = false }) { Text("Annuler", color = Vivi.Teal700) }
                }
            },
            containerColor = Vivi.White,
        )
    }
}

/** Carte « Adresse email » / « Téléphone » : valeur, badge Vérifié, usage, bouton Changer / Ajouter. */
@Composable
private fun CarteCoordonnee(
    icone: ImageVector, titre: String, valeur: String?, verifie: Boolean, canalActif: Boolean, champ: String, usage: String,
    onChanger: () -> Unit,
) {
    Carte {
        Column {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(icone, null, tint = Vivi.Teal700, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(8.dp))
                Text(titre, style = MaterialTheme.typography.titleMedium)
            }
            Spacer(Modifier.height(6.dp))
            if (valeur != null) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(valeur, style = MaterialTheme.typography.bodyLarge, fontWeight = FontWeight.SemiBold, color = Vivi.Ink, modifier = Modifier.weight(1f, fill = false))
                    if (verifie) { Spacer(Modifier.width(8.dp)); Puce("Vérifié", TonPuce.Succes, Icons.Outlined.Verified) }
                }
            } else {
                Text("Non renseigné.", style = MaterialTheme.typography.bodyLarge, color = Vivi.Muted)
            }
            Spacer(Modifier.height(6.dp))
            Text(usage, style = MaterialTheme.typography.bodySmall)
            Spacer(Modifier.height(10.dp))
            if (canalActif) {
                BoutonContour(
                    (if (valeur != null) "Changer" else "Ajouter") + if (champ == "email") " mon email" else " mon numéro",
                    onChanger,
                )
            } else {
                Text(
                    "L’envoi ${if (champ == "email") "d’emails" else "de SMS"} est désactivé pour le moment : modification impossible.",
                    style = MaterialTheme.typography.bodySmall, color = Vivi.Muted,
                )
            }
        }
    }
}

@Composable
fun LigneCompte(icone: ImageVector, titre: String, detail: String?, badge: String?, onClick: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().heightIn(min = 56.dp).clickable(role = Role.Button, onClick = onClick).padding(horizontal = 16.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(icone, null, tint = Vivi.Teal700)
        Spacer(Modifier.width(16.dp))
        Column(Modifier.weight(1f)) {
            Text(titre, style = MaterialTheme.typography.titleSmall)
            detail?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
        }
        badge?.let {
            Puce(it, TonPuce.Succes, Icons.Outlined.Verified)
            Spacer(Modifier.width(6.dp))
        }
        Icon(Icons.AutoMirrored.Outlined.KeyboardArrowRight, null, tint = Vivi.Muted)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FeuilleEdition(champ: String, u: Utilisateur, vm: ProfilViewModel, onFermer: () -> Unit) {
    var valeur by remember { mutableStateOf(when (champ) { "nom" -> u.nom; "email" -> u.email.orEmpty(); else -> u.telephone.orEmpty() }) }
    var code by remember { mutableStateOf("") }
    ModalBottomSheet(onDismissRequest = onFermer, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = Vivi.White) {
        Column(Modifier.padding(horizontal = 20.dp).padding(bottom = 24.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
            Text(when (champ) { "nom" -> "Votre nom"; "email" -> "Adresse email"; else -> "Numéro de téléphone" }, style = MaterialTheme.typography.headlineSmall)
            if (vm.codeEnvoye == null) {
                if (champ != "nom") Text("Un code sera envoyé à la nouvelle ${if (champ == "email") "adresse" else "ligne"} pour la confirmer.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)
                ChampTexte(valeur, { valeur = it }, when (champ) { "nom" -> "Nom et prénom"; "email" -> "Nouvelle adresse email"; else -> "Nouveau numéro" },
                    clavier = when (champ) { "email" -> KeyboardType.Email; "telephone" -> KeyboardType.Phone; else -> KeyboardType.Text }, erreur = vm.erreur)
                BoutonPrincipal(if (champ == "nom") "Enregistrer" else "Recevoir un code", {
                    if (champ == "nom") vm.nom(valeur, onFermer) else vm.demander(champ, valeur)
                }, Modifier.fillMaxWidth(), enCours = vm.enCours, actif = valeur.isNotBlank())
            } else {
                vm.codeDev?.let { if (code.isEmpty()) code = it }
                ChampTexte(code, { code = it.filter(Char::isDigit).take(6) }, "Code reçu", clavier = KeyboardType.NumberPassword, erreur = vm.erreur)
                BoutonPrincipal("Confirmer", { vm.confirmer(code, onFermer) }, Modifier.fillMaxWidth(), enCours = vm.enCours, actif = code.length == 6)
            }
        }
    }
}

/* ---------- Notifications ---------- */

class NotificationsViewModel : EcranViewModel<List<Notification>>() {
    /** null : toutes ; sinon « reservation », « paiement », « offre », « logement » ou « compte ». */
    var categorie by mutableStateOf<String?>(null)
        private set

    init { recharger() }
    override suspend fun charger() = appel { notifications(categorie = categorie) }.map { it.data }

    fun choisir(c: String?) {
        if (c == categorie) return
        categorie = c
        recharger()
    }

    fun supprimer(n: Notification) = action {
        appel { supprimerNotification(n.id) }.map {
            etat.donnees?.let { l -> remplacer(l.filter { it.id != n.id }) }
            Messages.afficher("Notification supprimée.")
        }
    }

    fun lire(n: Notification) {
        if (n.lue) return
        viewModelScope.launch { appel { lireNotification(n.id) }.onSuccess { remplacerLue(n.id) } }
    }

    fun toutLire() = action { appel { toutLire() }.map { etat.donnees?.let { l -> remplacer(l.map { it.copy(lue = true) }) }; Unit } }

    private fun remplacerLue(id: String) {
        etat.donnees?.let { l -> remplacer(l.map { if (it.id == id) it.copy(lue = true) else it }) }
    }
}

@Composable
fun EcranNotifications(onRetour: () -> Unit, onOuvrir: (Notification) -> Unit, vm: NotificationsViewModel = viewModel()) {
    Scaffold(containerColor = Vivi.Sand, topBar = {
        Column(Modifier.background(Vivi.White)) {
            BarreTitre("Notifications", onRetour, actions = {
                IconButton(onClick = vm::toutLire) { Icon(Icons.Outlined.DoneAll, contentDescription = "Tout marquer comme lu", tint = Vivi.Teal700) }
            })
            androidx.compose.foundation.lazy.LazyRow(contentPadding = PaddingValues(horizontal = 16.dp, vertical = 4.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                items(listOf(null to "Toutes", "reservation" to "Réservations", "paiement" to "Paiements", "offre" to "Offres", "logement" to "Logements", "compte" to "Compte")) { (valeur, libelle) ->
                    val choisi = vm.categorie == valeur
                    androidx.compose.material3.FilterChip(
                        selected = choisi, onClick = { vm.choisir(valeur) },
                        label = { Text(libelle, fontWeight = FontWeight.SemiBold, color = if (choisi) Vivi.White else Vivi.Body) },
                        shape = RoundedCornerShape(999.dp),
                        colors = androidx.compose.material3.FilterChipDefaults.filterChipColors(selectedContainerColor = Vivi.Teal700, containerColor = Vivi.White),
                    )
                }
            }
        }
    }) { marges ->
        AvecEtat(vm, Modifier.padding(marges)) { liste ->
            if (liste.isEmpty()) {
                Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState())) {
                    if (vm.categorie != null) EtatVide(Icons.Outlined.NotificationsNone, "Aucune notification de ce type")
                    else EtatVide(Icons.Outlined.NotificationsNone, "Aucune notification", "Confirmations, rappels et messages de VIVI apparaîtront ici.")
                }
            } else {
                LazyColumn(contentPadding = PaddingValues(vertical = 8.dp)) {
                    items(liste, key = { it.id }) { n ->
                        Row(
                            Modifier.fillMaxWidth().background(if (n.lue) Vivi.Sand else Vivi.White)
                                .clickable { vm.lire(n); onOuvrir(n) }.padding(start = 16.dp, top = 16.dp, bottom = 16.dp, end = 4.dp),
                        ) {
                            Box(Modifier.size(40.dp).clip(CircleShape).background(if (n.lue) Vivi.Segmented else Vivi.Teal50), contentAlignment = Alignment.Center) {
                                Icon(Icons.Outlined.Notifications, null, tint = Vivi.Teal700, modifier = Modifier.size(20.dp))
                            }
                            Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) {
                                Text(n.titre, style = MaterialTheme.typography.titleSmall, fontWeight = if (n.lue) FontWeight.Medium else FontWeight.Bold)
                                Text(n.texte, style = MaterialTheme.typography.bodyMedium)
                                Text(Fmt.quand(n.le), style = MaterialTheme.typography.bodySmall)
                            }
                            if (!n.lue) Box(Modifier.padding(top = 6.dp).size(10.dp).clip(CircleShape).background(Vivi.Orange400))
                            IconButton(onClick = { vm.supprimer(n) }, enabled = !vm.enCours) {
                                Icon(Icons.Outlined.DeleteOutline, contentDescription = "Supprimer la notification ${n.titre}", tint = Vivi.Muted, modifier = Modifier.size(20.dp))
                            }
                        }
                        Separateur()
                    }
                }
            }
        }
    }
}

/* ---------- Parrainage ---------- */

class ParrainageViewModel : EcranViewModel<Parrainage>() {
    init { recharger() }
    override suspend fun charger() = appel { parrainage() }.map { it.data }
}

@Composable
fun EcranParrainage(onRetour: () -> Unit, vm: ParrainageViewModel = viewModel()) {
    val context = LocalContext.current
    Scaffold(containerColor = Vivi.Sand, topBar = { BarreTitre("Parrainage", onRetour) }) { marges ->
        AvecEtat(vm, Modifier.padding(marges)) { p ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                if (!p.actif) Bandeau("Le parrainage est en pause pour le moment.", ton = TonPuce.Orange)
                Carte(fond = Vivi.Teal900) {
                    Column {
                        Text("Invitez vos proches", style = MaterialTheme.typography.headlineSmall.copy(color = Vivi.White))
                        Spacer(Modifier.height(6.dp))
                        Text("Vous recevez ${Fmt.fcfa(p.bonusParrain)} au premier séjour de chaque ami" +
                            if (p.bonusFilleul > 0) ", et lui ${Fmt.fcfa(p.bonusFilleul)} à l’inscription." else ".", color = Vivi.Teal100)
                        p.code?.let {
                            Spacer(Modifier.height(16.dp))
                            Box(Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(Vivi.White.copy(alpha = .12f)).padding(14.dp), contentAlignment = Alignment.Center) {
                                Text(it, style = MaterialTheme.typography.headlineMedium.copy(color = Vivi.White), fontWeight = FontWeight.Bold)
                            }
                        }
                        p.messagePartage?.let { m ->
                            Spacer(Modifier.height(16.dp))
                            BoutonPrincipal("Partager mon code", { partager(context, m) }, Modifier.fillMaxWidth(), icone = Icons.Outlined.Share)
                        }
                    }
                }
                Carte {
                    Column {
                        TitreSection("Mon bonus")
                        Text(Fmt.fcfa(p.disponible), style = MaterialTheme.typography.headlineMedium)
                        Text("Utilisable au paiement de vos réservations.", style = MaterialTheme.typography.bodySmall)
                    }
                }
                if (p.filleuls.isNotEmpty()) {
                    Carte {
                        Column {
                            TitreSection("Mes filleuls")
                            p.filleuls.forEach { f ->
                                Row(Modifier.fillMaxWidth().padding(vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Text(f.prenom, style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
                                    Puce(if (f.recompense) "Bonus reçu" else "En attente du séjour", if (f.recompense) TonPuce.Succes else TonPuce.Neutre)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

/** Réglage « Offres de VIVI » (PUT /profil/preferences). */
@Composable
private fun CarteOffres(actif: Boolean, enCours: Boolean, onChanger: (Boolean) -> Unit) {
    Carte(remplissage = PaddingValues(vertical = 4.dp)) {
        Row(
            Modifier.fillMaxWidth().heightIn(min = 64.dp)
                .toggleable(actif, enabled = !enCours, role = Role.Switch) { onChanger(it) }
                .padding(horizontal = 16.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Outlined.CardGiftcard, null, tint = Vivi.Teal700)
            Spacer(Modifier.width(16.dp))
            Column(Modifier.weight(1f)) {
                Text("Offres de VIVI", style = MaterialTheme.typography.titleSmall)
                Text(
                    "Promotions et nouveautés dans vos notifications. Les messages sur vos réservations et paiements arrivent toujours.",
                    style = MaterialTheme.typography.bodySmall,
                )
            }
            Spacer(Modifier.width(8.dp))
            androidx.compose.material3.Switch(actif, null, enabled = !enCours,
                colors = androidx.compose.material3.SwitchDefaults.colors(checkedTrackColor = Vivi.Teal700))
        }
    }
}

/** Réglage « Verrouiller VIVI avec l'empreinte » : activé seulement après une empreinte réussie. */
@Composable
private fun CarteSecurite() {
    val context = LocalContext.current
    var actif by remember { mutableStateOf(bj.vivi.app.ui.Verrou.actif) }
    val disponible = remember { bj.vivi.app.ui.Verrou.disponible(context) }
    Carte(remplissage = PaddingValues(vertical = 4.dp)) {
        Row(
            Modifier.fillMaxWidth().heightIn(min = 64.dp)
                .toggleable(actif, enabled = disponible, role = androidx.compose.ui.semantics.Role.Switch) { voulu ->
                    bj.vivi.app.ui.Verrou.actif = voulu
                    actif = voulu
                    Messages.afficher(if (voulu) "VIVI se verrouillera à l’ouverture et après 1 minute hors de l’application." else "Verrouillage désactivé.")
                }
                .padding(horizontal = 16.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Outlined.Fingerprint, null, tint = Vivi.Teal700)
            Spacer(Modifier.width(16.dp))
            Column(Modifier.weight(1f)) {
                Text("Verrouiller avec l’empreinte", style = MaterialTheme.typography.titleSmall)
                Text(
                    if (disponible) "Protège votre compte connecté : empreinte demandée à l’ouverture et après 1 min hors de l’application."
                    else "Configurez une empreinte ou un code dans les réglages du téléphone pour l’activer.",
                    style = MaterialTheme.typography.bodySmall,
                )
            }
            Spacer(Modifier.width(8.dp))
            androidx.compose.material3.Switch(actif, null, enabled = disponible,
                colors = androidx.compose.material3.SwitchDefaults.colors(checkedTrackColor = Vivi.Teal700))
        }
    }
}
