package bj.vivi.app.ui.auth

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.credentials.CredentialManager
import androidx.credentials.CustomCredential
import androidx.credentials.GetCredentialRequest
import androidx.credentials.exceptions.GetCredentialCancellationException
import androidx.credentials.exceptions.GetCredentialException
import bj.vivi.app.R
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.selection.selectable
import androidx.compose.ui.semantics.Role
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.animation.core.animateFloat
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.AlternateEmail
import androidx.compose.material.icons.outlined.Apartment
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.Luggage
import androidx.compose.material.icons.outlined.MarkEmailRead
import androidx.compose.material.icons.outlined.PhoneIphone
import androidx.compose.material.icons.outlined.Place
import androidx.compose.material.icons.outlined.Public
import androidx.compose.material.icons.outlined.Sms
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.Catalogue
import bj.vivi.app.data.DemandeCode
import bj.vivi.app.data.ErreurVivi
import bj.vivi.app.data.Reseau
import bj.vivi.app.data.appel
import bj.vivi.app.data.corps
import bj.vivi.app.ui.Messages
import bj.vivi.app.ui.composants.Bandeau
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.BoutonTexte
import bj.vivi.app.ui.composants.ChampTexte
import bj.vivi.app.ui.composants.Marque
import bj.vivi.app.ui.composants.Segmente
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.theme.Outfit
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

/**
 * Connexion et création de compte sans mot de passe (AuthController) :
 * 1. identifiant (email ou numéro béninois) → un code à 6 chiffres est envoyé ;
 * 2. code → jeton gardé dans le stockage chiffré. La navigation suit alors la session.
 */
class ConnexionViewModel : ViewModel() {
    var mode by mutableStateOf("connexion")
    /**
     * Où habite la personne : « benin » (numéro béninois OU email, au choix) ;
     * « international » (email uniquement).
     */
    var lieu by mutableStateOf("benin")
        private set
    var identifiant by mutableStateOf("")
    var nom by mutableStateOf("")
    var role by mutableStateOf("client")
    var invitation by mutableStateOf("")
    var code by mutableStateOf("")
    var demande by mutableStateOf<DemandeCode?>(null)
        private set
    var erreurs by mutableStateOf<Map<String, String>>(emptyMap())
        private set
    var enCours by mutableStateOf(false)
        private set
    var renvoiDans by mutableIntStateOf(0)
        private set
    var canaux by mutableStateOf(Pair(true, false))
        private set
    var parrainageActif by mutableStateOf(false)
        private set
    var bonusBienvenue by mutableIntStateOf(0)
        private set
    /** Identifiant client Web Google ; null = connexion Google désactivée sur la plateforme. */
    var googleClientId by mutableStateOf<String?>(null)
        private set
    var enCoursGoogle by mutableStateOf(false)

    init {
        viewModelScope.launch {
            Catalogue.obtenir()?.let {
                canaux = it.connexion.email to it.connexion.sms
                googleClientId = it.connexion.googleClientId?.takeIf { _ -> it.connexion.google }
                parrainageActif = it.parrainage.actif
                bonusBienvenue = it.parrainage.bonusFilleul
            }
        }
    }

    /** Le numéro béninois est accepté au Bénin, si les SMS sont actifs ; ailleurs, email seulement. */
    val numeroAccepte get() = canaux.second && (lieu == "benin" || !canaux.first)
    val emailAccepte get() = canaux.first

    private var lieuChoisi = false

    /** Choix proposé d'après le pays de la carte SIM (ou du réseau) : hors du Bénin → email. La personne peut en changer. */
    fun proposerLieu(pays: String?) {
        if (lieuChoisi || identifiant.isNotEmpty() || pays.isNullOrBlank()) return
        lieu = if (pays.equals("bj", ignoreCase = true)) "benin" else "international"
    }

    /** Onglet proposé quand le serveur répond « aucun compte » ou « compte déjà existant » pour cet identifiant. */
    val autreMode: String?
        get() = erreurs["identifiant"]?.let {
            when {
                mode == "connexion" && it.startsWith("Aucun compte") -> "inscription"
                mode == "inscription" && it.startsWith("Un compte existe déjà") -> "connexion"
                else -> null
            }
        }

    fun choisirMode(m: String) {
        mode = m
        erreurs = emptyMap()
    }

    /** Nouvelle saisie : l'erreur précédente ne s'applique plus. */
    fun saisirIdentifiant(saisie: String) {
        identifiant = saisie
        erreurs = erreurs - "identifiant" - "general"
    }

    fun choisirLieu(l: String) {
        lieuChoisi = true
        if (l == lieu) return
        lieu = l
        if (l == "international" && !identifiant.contains('@')) identifiant = ""   // un numéro n'a pas sa place dans le champ email
        erreurs = erreurs - "identifiant" - "general"
    }

    /** « +229 01 97 00 00 00 » → « 0197000000 » ; null si la saisie n'est pas un numéro béninois complet. */
    private val numero: String?
        get() = identifiant.takeIf { s -> s.all { it.isDigit() || it in " +-.()" } }?.filter(Char::isDigit)
            ?.let { if (it.length > 10) it.removePrefix("00").removePrefix("229") else it }
            ?.takeIf { it.length == 10 && it.startsWith("01") }

    private val estEmail get() = Regex("^[^@\\s]+@[^@\\s]+\\.[^@\\s]{2,}$").matches(identifiant.trim())

    /** Saisie prête à envoyer : numéro béninois complet (10 chiffres commençant par 01) ou email plausible. */
    val identifiantValide get() = (numeroAccepte && numero != null) || (emailAccepte && estEmail)

    fun envoyer() {
        if (enCours) return
        erreurs = emptyMap()
        viewModelScope.launch {
            enCours = true
            appel {
                demanderCode(corps(
                    "mode" to mode, "pays" to lieu.takeIf { emailAccepte }, "identifiant" to (numero ?: identifiant.trim()),
                    "nom" to nom.trim().takeIf { mode == "inscription" },
                    "role" to role.takeIf { mode == "inscription" },
                    "invitation" to invitation.trim().takeIf { mode == "inscription" && it.isNotEmpty() },
                ))
            }.onSuccess {
                demande = it
                code = it.codeDev.orEmpty()   // en local seulement : code renvoyé par le serveur pour tester
                compterRenvoi(it.renvoiSecondes)
            }.onFailure { echec(it as ErreurVivi) }
            enCours = false
        }
    }

    fun renvoyer() {
        val d = demande ?: return
        viewModelScope.launch {
            appel { renvoyerCode(corps("demande" to d.demande)) }
                .onSuccess { r ->
                    Messages.afficher(r.message ?: "Un nouveau code vient de vous être envoyé.")
                    r.codeDev?.let { code = it }
                    compterRenvoi(d.renvoiSecondes)
                }
                .onFailure { echec(it as ErreurVivi) }
        }
    }

    fun verifier() {
        val d = demande ?: return
        if (enCours || code.length != 6) return
        erreurs = emptyMap()
        viewModelScope.launch {
            enCours = true
            appel { verifierCode(corps("demande" to d.demande, "code" to code, "appareil" to Reseau.nomAppareil)) }
                .onSuccess {
                    Reseau.session.ouvrir(it)
                    Messages.afficher(if (it.nouveauCompte) "Bienvenue sur VIVI, ${it.utilisateur.prenom} !" else "${bj.vivi.app.ui.Fmt.salutation()} ${it.utilisateur.prenom} !")
                }
                .onFailure {
                    val e = it as ErreurVivi
                    // Demande expirée : retour à la première étape
                    if (e.champ("demande") != null) demande = null
                    echec(e)
                }
            enCours = false
        }
    }

    /** Jeton Google obtenu sur le téléphone → compte CLIENT VIVI (connexion ou création). */
    fun connexionGoogle(idToken: String) {
        erreurs = emptyMap()
        viewModelScope.launch {
            enCoursGoogle = true
            appel {
                connexionGoogle(corps(
                    "id_token" to idToken, "appareil" to Reseau.nomAppareil,
                    "invitation" to invitation.trim().takeIf { mode == "inscription" && it.isNotEmpty() },
                ))
            }.onSuccess {
                Reseau.session.ouvrir(it)
                Messages.afficher(if (it.nouveauCompte) "Bienvenue sur VIVI, ${it.utilisateur.prenom} !" else "${bj.vivi.app.ui.Fmt.salutation()} ${it.utilisateur.prenom} !")
            }.onFailure {
                // Message affiché sous le bouton Google (le serveur le range sur « identifiant »)
                val e = it as ErreurVivi
                erreurGoogle(e.champs.values.firstOrNull() ?: e.message)
            }
            enCoursGoogle = false
        }
    }

    fun erreurGoogle(message: String) {
        erreurs = mapOf("google" to message)
    }

    fun modifierIdentifiant() {
        demande = null
        code = ""
        erreurs = emptyMap()
    }

    private fun echec(e: ErreurVivi) {
        erreurs = e.champs.ifEmpty { mapOf("general" to e.message) }
    }

    private fun compterRenvoi(secondes: Int) {
        viewModelScope.launch {
            renvoiDans = secondes
            while (renvoiDans > 0) {
                delay(1000)
                renvoiDans--
            }
        }
    }
}

@Composable
fun EcranConnexion(onFermer: () -> Unit, modeInitial: String = "connexion", vm: ConnexionViewModel = viewModel()) {
    LaunchedEffect(Unit) { if (vm.demande == null && vm.identifiant.isEmpty()) vm.mode = modeInitial }
    // Connecté (code ou Google) : retour à l'écran d'où l'on venait, par exemple la fiche à réserver
    val connecte = Reseau.session.utilisateur.collectAsState().value != null
    LaunchedEffect(connecte) { if (connecte) onFermer() }
    val contexte = LocalContext.current
    LaunchedEffect(Unit) {
        val tel = contexte.getSystemService(android.telephony.TelephonyManager::class.java)
        vm.proposerLieu(tel?.simCountryIso?.ifBlank { tel.networkCountryIso })
    }

    Column(
        Modifier.fillMaxSize().background(Vivi.White).statusBarsPadding().navigationBarsPadding().imePadding()
            .verticalScroll(rememberScrollState()).padding(horizontal = 20.dp),
    ) {
        Row(Modifier.fillMaxWidth().padding(vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = { if (vm.demande != null) vm.modifierIdentifiant() else onFermer() }) {
                Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Retour", tint = Vivi.Ink)
            }
            Spacer(Modifier.weight(1f))
            Marque(taille = 30.dp)
            Spacer(Modifier.weight(1f))
            Spacer(Modifier.size(48.dp))
        }
        Spacer(Modifier.height(16.dp))

        val demande = vm.demande
        if (demande == null) Saisie(vm) else SaisieCode(vm, demande)
        Spacer(Modifier.height(24.dp))
    }
}

@Composable
private fun Saisie(vm: ConnexionViewModel) {
    // Ordre : où êtes-vous → identifiant → bouton, puis Google en second choix. Une personne à l'étranger
    // sans compte Gmail voit ainsi tout de suite le champ email (n'importe quelle messagerie).
    val inscription = vm.mode == "inscription"
    val (email, sms) = vm.canaux
    Segmente(listOf("connexion" to "Se connecter", "inscription" to "Créer un compte"), vm.mode, { vm.choisirMode(it) })
    Spacer(Modifier.height(20.dp))
    Text(if (inscription) "Créer votre compte" else "Bon retour", style = MaterialTheme.typography.headlineLarge)
    Spacer(Modifier.height(8.dp))
    Text(
        when {
            vm.numeroAccepte && email -> "Recevez un code à 6 chiffres par SMS ou par email."
            vm.numeroAccepte -> "Recevez un code à 6 chiffres par SMS."
            else -> "Recevez un code à 6 chiffres par email."
        } + " Pas de mot de passe à retenir.",
        style = MaterialTheme.typography.bodyLarge, color = Vivi.Muted,
    )
    Spacer(Modifier.height(20.dp))

    // Au Bénin : numéro béninois (SMS) ou email, au choix ; à l'international : email uniquement
    if (email && sms) {
        Text("Où êtes-vous ?", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
        Spacer(Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            CarteRole("Au Bénin", "Numéro ou email", Icons.Outlined.Place, vm.lieu == "benin", Modifier.weight(1f)) { vm.choisirLieu("benin") }
            CarteRole("International", "Avec votre email", Icons.Outlined.Public, vm.lieu == "international", Modifier.weight(1f)) { vm.choisirLieu("international") }
        }
        Spacer(Modifier.height(16.dp))
    }

    if (inscription) {
        ChampTexte(vm.nom, { vm.nom = it }, "Nom et prénom *", indication = "Awa Dossou", erreur = vm.erreurs["nom"])
        Spacer(Modifier.height(16.dp))
    }

    if (vm.numeroAccepte) {
        // Un seul champ : numéro béninois (code par SMS) ou adresse email (code par email)
        ChampTexte(
            vm.identifiant, { vm.saisirIdentifiant(it.trimStart()) },
            libelle = if (email) "Numéro béninois ou email *" else "Numéro béninois *",
            indication = if (email) "01 97 00 00 00 ou vous@exemple.com" else "01 97 00 00 00",
            aide = "Numéro : 10 chiffres commençant par 01." + if (email) " Le code arrive par SMS ou par email." else " Le code arrive par SMS.",
            erreur = vm.erreurs["identifiant"],
            clavier = if (email) KeyboardType.Email else KeyboardType.Phone,
            icone = Icons.Outlined.PhoneIphone,
        )
    } else {
        ChampTexte(
            vm.identifiant, { vm.saisirIdentifiant(it.trim()) },
            libelle = "Adresse email *",
            indication = "vous@exemple.com",
            aide = "Toutes les messageries sont acceptées : Outlook, Yahoo, iCloud, Gmail, adresse professionnelle…",
            erreur = vm.erreurs["identifiant"],
            clavier = KeyboardType.Email,
            icone = Icons.Outlined.AlternateEmail,
        )
    }
    // Identifiant inconnu en connexion (ou déjà inscrit en création) : un appui change d'onglet, la saisie est gardée
    vm.autreMode?.let { autre ->
        BoutonTexte(
            if (autre == "inscription") "Créer un compte avec cet identifiant" else "Me connecter avec cet identifiant",
            { vm.choisirMode(autre) },
        )
    }

    if (inscription) {
        Spacer(Modifier.height(16.dp))
        Text("Vous êtes", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
        Spacer(Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            CarteRole("Client", "Je cherche un logement", Icons.Outlined.Luggage, vm.role == "client", Modifier.weight(1f)) { vm.role = "client" }
            CarteRole("Propriétaire", "Je publie mes logements", Icons.Outlined.Apartment, vm.role == "proprietaire", Modifier.weight(1f)) { vm.role = "proprietaire" }
        }
        if (vm.parrainageActif) {
            Spacer(Modifier.height(16.dp))
            ChampTexte(
                vm.invitation, { vm.invitation = it.uppercase() }, "Code d’invitation (facultatif)",
                indication = "Ex. GERA-7K3P",
                aide = "Un ami vous a invité ? Saisissez son code" +
                    (if (vm.bonusBienvenue > 0) " : vous recevez ${bj.vivi.app.ui.Fmt.fcfa(vm.bonusBienvenue)} de bonus sur votre première réservation." else "."),
                erreur = vm.erreurs["invitation"],
            )
        }
    }

    vm.erreurs["general"]?.let {
        Spacer(Modifier.height(16.dp))
        Bandeau(it, ton = TonPuce.Danger, icone = Icons.Outlined.Info)
    }
    Spacer(Modifier.height(20.dp))
    BoutonPrincipal(
        if (inscription) "Créer mon compte" else "Se connecter", vm::envoyer, Modifier.fillMaxWidth(), enCours = vm.enCours,
        actif = vm.identifiantValide && (!inscription || vm.nom.trim().length >= 3),
    )
    Spacer(Modifier.height(12.dp))
    Text(
        "Vous recevrez un code à 6 chiffres pour confirmer. " +
            "En continuant, vous acceptez les conditions générales de VIVI.",
        style = MaterialTheme.typography.bodySmall, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth(),
    )

    // Google : comptes clients seulement, donc absent pour une inscription propriétaire (comme sur le site)
    // Version de test : bouton visible même sans identifiants Google sur la plateforme (un appui explique quoi configurer)
    val clientGoogle = vm.googleClientId
    if ((clientGoogle != null || bj.vivi.app.BuildConfig.DEBUG) && !(inscription && vm.role == "proprietaire")) {
        Spacer(Modifier.height(16.dp))
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.weight(1f).height(1.dp).background(Vivi.Line))
            Text("ou avec Gmail", style = MaterialTheme.typography.bodySmall, color = Vivi.Muted, modifier = Modifier.padding(horizontal = 12.dp))
            Box(Modifier.weight(1f).height(1.dp).background(Vivi.Line))
        }
        Spacer(Modifier.height(16.dp))
        BoutonGoogle(vm, clientGoogle, if (inscription) "S’inscrire avec Google" else "Se connecter avec Google")
        Spacer(Modifier.height(8.dp))
        Text(
            if (inscription) "Compte client créé en un clic avec votre adresse Gmail."
            else "Pour les clients. Les propriétaires et les gestionnaires se connectent avec le code.",
            style = MaterialTheme.typography.bodySmall, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth(),
        )
        vm.erreurs["google"]?.let {
            Spacer(Modifier.height(10.dp))
            Bandeau(it, ton = TonPuce.Danger, icone = Icons.Outlined.Info)
        }
    }
}

/**
 * « Continuer avec Google » : Credential Manager affiche le choix du compte Google du téléphone
 * et renvoie un id_token (audience = identifiant client Web), vérifié ensuite par la plateforme.
 */
@Composable
private fun BoutonGoogle(vm: ConnexionViewModel, clientWeb: String?, libelle: String) {
    val contexte = LocalContext.current
    val portee = rememberCoroutineScope()
    val forme = RoundedCornerShape(14.dp)
    Row(
        Modifier.fillMaxWidth().height(54.dp).clip(forme)
            .border(1.dp, Vivi.LineStrong, forme).background(Vivi.White)
            .clickable(enabled = !vm.enCoursGoogle, role = Role.Button) {
                if (clientWeb == null) {
                    vm.erreurGoogle("Connexion Google pas encore activée : ajoutez GOOGLE_CLIENT_ID (et GOOGLE_CLIENTS_ANDROID) dans le .env de la plateforme.")
                    return@clickable
                }
                portee.launch {
                    vm.enCoursGoogle = true
                    try {
                        val demande = GetCredentialRequest.Builder()
                            .addCredentialOption(GetSignInWithGoogleOption.Builder(clientWeb).build())
                            .build()
                        val reponse = CredentialManager.create(contexte).getCredential(contexte, demande)
                        val cred = reponse.credential
                        if (cred is CustomCredential && cred.type == GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL) {
                            vm.connexionGoogle(GoogleIdTokenCredential.createFrom(cred.data).idToken)
                        } else {
                            vm.erreurGoogle("Réponse inattendue de Google. Réessayez ou utilisez le code.")
                            vm.enCoursGoogle = false
                        }
                    } catch (e: GetCredentialCancellationException) {
                        // Fenêtre fermée par la personne, ou refus de Google « [16] » (souvent : SHA-1 de l'APK absent du client Android)
                        android.util.Log.w("VIVI", "Google annulé : ${e.message}")
                        if (e.message?.contains("[16]") == true) vm.erreurGoogle("Google a refusé la connexion. Utilisez la connexion par code.")
                        vm.enCoursGoogle = false
                    } catch (e: GetCredentialException) {
                        vm.erreurGoogle("Connexion Google impossible sur ce téléphone. Utilisez la connexion par code.")
                        android.util.Log.w("VIVI", "Google : ${e.type} ${e.message}")
                        vm.enCoursGoogle = false
                    } catch (e: Exception) {
                        vm.erreurGoogle("Connexion Google impossible. Réessayez ou utilisez le code.")
                        android.util.Log.w("VIVI", "Google", e)
                        vm.enCoursGoogle = false
                    }
                }
            },
        horizontalArrangement = Arrangement.Center, verticalAlignment = Alignment.CenterVertically,
    ) {
        if (vm.enCoursGoogle) {
            CircularProgressIndicator(Modifier.size(20.dp), color = Vivi.Teal700, strokeWidth = 2.dp)
        } else {
            Icon(painterResource(R.drawable.ic_google), contentDescription = null, tint = Color.Unspecified, modifier = Modifier.size(20.dp))
        }
        Spacer(Modifier.size(12.dp))
        Text(libelle, style = MaterialTheme.typography.titleMedium, color = Vivi.Ink)
    }
}

@Composable
private fun CarteRole(titre: String, texte: String, icone: androidx.compose.ui.graphics.vector.ImageVector, choisi: Boolean, modifier: Modifier, onClick: () -> Unit) {
    val forme = RoundedCornerShape(14.dp)
    Column(
        modifier.clip(forme)
            .border(if (choisi) 2.dp else 1.dp, if (choisi) Vivi.Teal700 else Vivi.LineStrong, forme)
            .background(if (choisi) Vivi.Teal50 else Vivi.White)
            .selectable(selected = choisi, role = Role.RadioButton, onClick = onClick)
            .padding(14.dp),
    ) {
        Icon(icone, null, tint = if (choisi) Vivi.Teal700 else Vivi.Muted)
        Spacer(Modifier.height(8.dp))
        Text(titre, style = MaterialTheme.typography.titleMedium)
        Text(texte, style = MaterialTheme.typography.bodySmall)
    }
}

@Composable
private fun SaisieCode(vm: ConnexionViewModel, demande: DemandeCode) {
    val focus = remember { FocusRequester() }
    val clavier = androidx.compose.ui.platform.LocalSoftwareKeyboardController.current
    LaunchedEffect(Unit) { runCatching { focus.requestFocus(); clavier?.show() } }
    LaunchedEffect(vm.code) { if (vm.code.length == 6) vm.verifier() }
    val parSms = demande.canal == "sms"
    val erreur = vm.erreurs["code"] ?: vm.erreurs["general"] ?: vm.erreurs["identifiant"]

    // Code refusé : les cases tremblent et passent en rouge
    val tremblement = remember { androidx.compose.animation.core.Animatable(0f) }
    LaunchedEffect(erreur) {
        if (erreur != null) {
            listOf(14f, -12f, 9f, -6f, 3f, 0f).forEach { tremblement.animateTo(it, androidx.compose.animation.core.tween(45)) }
        }
    }
    // Curseur qui clignote dans la case active
    val clignote by androidx.compose.animation.core.rememberInfiniteTransition(label = "curseur").animateFloat(
        1f, 0f, androidx.compose.animation.core.infiniteRepeatable<Float>(androidx.compose.animation.core.tween(530), androidx.compose.animation.core.RepeatMode.Reverse), label = "curseur",
    )

    Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
        Spacer(Modifier.height(8.dp))
        Box(Modifier.size(76.dp).clip(androidx.compose.foundation.shape.CircleShape).background(Vivi.Teal50), contentAlignment = Alignment.Center) {
            Icon(if (parSms) Icons.Outlined.Sms else Icons.Outlined.MarkEmailRead, null, tint = Vivi.Teal700, modifier = Modifier.size(34.dp))
        }
        Spacer(Modifier.height(18.dp))
        Text("Entrez le code", style = MaterialTheme.typography.headlineLarge, textAlign = TextAlign.Center)
        Spacer(Modifier.height(8.dp))
        Text(
            "Nous avons envoyé un code à 6 chiffres ${if (parSms) "par SMS au" else "à"}",
            style = MaterialTheme.typography.bodyLarge, color = Vivi.Muted, textAlign = TextAlign.Center,
        )
        Spacer(Modifier.height(4.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                if (parSms) "+229 ${demande.destination}" else demande.destination,
                style = MaterialTheme.typography.titleMedium, color = Vivi.Ink, textAlign = TextAlign.Center,
                modifier = Modifier.weight(1f, fill = false),
            )
            BoutonTexte("Modifier", vm::modifierIdentifiant)
        }
    }
    Spacer(Modifier.height(20.dp))

    // Six cases, un seul champ réel (copier-coller et remplissage automatique possibles)
    BasicTextField(
        value = vm.code,
        onValueChange = { v -> vm.code = v.filter(Char::isDigit).take(6) },
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
        modifier = Modifier.fillMaxWidth().focusRequester(focus)
            .graphicsLayer { translationX = tremblement.value * density }
            .semantics { contentDescription = "Code à 6 chiffres, ${vm.code.length} saisis" },
        decorationBox = {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                repeat(6) { i ->
                    val chiffre = vm.code.getOrNull(i)?.toString()
                    val active = i == vm.code.length && !vm.enCours
                    val bord = when {
                        erreur != null -> Vivi.Danger
                        active -> Vivi.Teal700
                        chiffre != null -> Vivi.Teal700.copy(alpha = .45f)
                        else -> Vivi.LineStrong
                    }
                    Box(
                        Modifier.weight(1f).height(60.dp).clip(RoundedCornerShape(14.dp))
                            .background(if (chiffre != null && erreur == null) Vivi.Teal50 else Vivi.White)
                            .border(if (active || erreur != null) 2.dp else 1.dp, bord, RoundedCornerShape(14.dp)),
                        contentAlignment = Alignment.Center,
                    ) {
                        if (chiffre != null) {
                            Text(chiffre, fontFamily = Outfit, fontWeight = FontWeight.Bold, fontSize = 26.sp, color = if (erreur != null) Vivi.Danger else Vivi.Ink)
                        } else if (active) {
                            Box(Modifier.width(2.dp).height(26.dp).graphicsLayer { alpha = clignote }.background(Vivi.Teal700))
                        }
                    }
                }
            }
        },
    )
    Spacer(Modifier.height(10.dp))
    when {
        erreur != null -> Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Outlined.Info, null, tint = Vivi.Danger, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(6.dp))
            Text(erreur, style = MaterialTheme.typography.bodyMedium.copy(color = Vivi.Danger))
        }
        vm.enCours -> Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center, verticalAlignment = Alignment.CenterVertically) {
            CircularProgressIndicator(Modifier.size(16.dp), color = Vivi.Teal700, strokeWidth = 2.dp)
            Spacer(Modifier.width(8.dp))
            Text("Vérification…", style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)
        }
        else -> Text(
            "Le code est valable ${demande.validiteMinutes} minutes.",
            style = MaterialTheme.typography.bodySmall, color = Vivi.Muted, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth(),
        )
    }
    if (demande.codeDev != null) {
        Spacer(Modifier.height(12.dp))
        Bandeau("Mode test : code ${demande.codeDev} rempli automatiquement.", ton = TonPuce.Orange, icone = Icons.Outlined.Info)
    }
    Spacer(Modifier.height(24.dp))
    BoutonPrincipal("Valider", vm::verifier, Modifier.fillMaxWidth(), actif = vm.code.length == 6, enCours = vm.enCours)
    Spacer(Modifier.height(20.dp))

    // Renvoi du code
    Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(Vivi.Sand).padding(14.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
            if (parSms) "Vous n’avez pas reçu le SMS ?" else "Vous n’avez pas reçu l’email ? Regardez aussi dans les indésirables.",
            style = MaterialTheme.typography.bodySmall, textAlign = TextAlign.Center,
        )
        if (vm.renvoiDans > 0) {
            Text(
                "Nouveau code possible dans %d:%02d".format(vm.renvoiDans / 60, vm.renvoiDans % 60),
                style = MaterialTheme.typography.labelLarge, color = Vivi.Muted, modifier = Modifier.padding(top = 10.dp, bottom = 4.dp),
            )
        } else {
            BoutonTexte("Envoyer un nouveau code", vm::renvoyer)
        }
    }
}
