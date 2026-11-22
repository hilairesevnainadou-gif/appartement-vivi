package bj.vivi.app.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement

/*
 * Modèles des réponses de l'API mobile (plateforme/docs/API-mobile.md, app/Http/Resources/Api).
 * Montants : entiers en FCFA. Dates : ISO 8601. Énumérations : {valeur, libelle}.
 * Tous les champs sont facultatifs côté application : un champ absent ne fait jamais planter l'écran.
 */

@Serializable
data class Enumeration(
    val valeur: String = "",
    val libelle: String = "",
    /** Types d’espace (configuration) : « logement » ou « salle », et capacité maximale (20 ou 1 000). */
    val categorie: String? = null,
    @SerialName("capacite_max") val capaciteMax: Int? = null,
) {
    val estSalle get() = categorie == "salle"
}

@Serializable
data class Enveloppe<T>(val data: T, val message: String? = null)

@Serializable
data class Liste<T>(val data: List<T> = emptyList(), val meta: Meta? = null, val message: String? = null)

/** Pagination : l'API renvoie soit le format Laravel (current_page…), soit le format VIVI (page…). */
@Serializable
data class Meta(
    val page: Int? = null,
    @SerialName("derniere_page") val dernierePage: Int? = null,
    @SerialName("current_page") val currentPage: Int? = null,
    @SerialName("last_page") val lastPage: Int? = null,
    val total: Int? = null,
    @SerialName("non_lues") val nonLues: Int? = null,
    val onglet: String? = null,
    val compteurs: Map<String, Int>? = null,
) {
    val pageCourante get() = page ?: currentPage ?: 1
    val pageMax get() = dernierePage ?: lastPage ?: 1
}

@Serializable
data class Message(val message: String? = null)

/* ---------- Compte ---------- */

@Serializable
data class Utilisateur(
    val id: Long = 0,
    val nom: String = "",
    val prenom: String = "",
    val initiales: String = "",
    val email: String? = null,
    val telephone: String? = null,
    @SerialName("email_verifie") val emailVerifie: Boolean = false,
    @SerialName("telephone_verifie") val telephoneVerifie: Boolean = false,
    val roles: List<String> = emptyList(),
    @SerialName("libelle_role") val libelleRole: String = "",
    @SerialName("est_proprietaire") val estProprietaire: Boolean = false,
    @SerialName("est_gestionnaire") val estGestionnaire: Boolean = false,
    @SerialName("est_agent") val estAgent: Boolean = false,
    val espace: String = "client",
    @SerialName("membre_depuis") val membreDepuis: String? = null,
    /** Offres de VIVI (promotions, nouveautés) ; les notifications de réservation arrivent toujours. */
    @SerialName("recevoir_offres") val recevoirOffres: Boolean = true,
    /** Dernier numéro WhatsApp donné à une réservation (format international), pour préremplir la suivante. */
    val whatsapp: String? = null,
) {
    val estPro get() = espace == "pro"
    /** Agent marketing sans rôle pro : l'application ouvre son espace agent. */
    val estEspaceAgent get() = espace == "agent"
}

@Serializable
data class DemandeCode(
    val demande: String = "",
    val canal: String = "mail",
    val destination: String = "",
    @SerialName("renvoi_secondes") val renvoiSecondes: Int = 30,
    @SerialName("validite_minutes") val validiteMinutes: Int = 10,
    @SerialName("code_dev") val codeDev: String? = null,
)

@Serializable
data class Renvoi(val message: String? = null, @SerialName("code_dev") val codeDev: String? = null)

@Serializable
data class Connexion(
    val jeton: String,
    @SerialName("expire_le") val expireLe: String? = null,
    @SerialName("nouveau_compte") val nouveauCompte: Boolean = false,
    val utilisateur: Utilisateur,
)

@Serializable
data class Profil(val data: Utilisateur, val stats: Stats? = null) {
    @Serializable
    data class Stats(val reservations: Int = 0)
}

@Serializable
data class ChangementDemande(val message: String? = null, @SerialName("code_dev") val codeDev: String? = null)

/** GET /compte/suppression : obstacles (aucun bouton tant qu'il en reste) ou pertes à accepter. */
@Serializable
data class SuppressionCompte(
    val possible: Boolean = false,
    val obstacles: List<Obstacle> = emptyList(),
    val pertes: List<String> = emptyList(),
    /** mail ou sms */
    val canal: String? = null,
    val destination: String? = null,
) {
    @Serializable
    data class Obstacle(val code: String = "", val message: String = "")
}

@Serializable
data class Notification(
    val id: String,
    val titre: String = "",
    val texte: String = "",
    val lien: String? = null,
    val action: String? = null,
    val icone: String? = null,
    val type: String? = null,
    /** reservation, paiement, offre, logement, compte */
    val categorie: String? = null,
    /** Écran de l'application à ouvrir (voir docs/API-mobile.md § Notifications). */
    val cible: Cible? = null,
    val lue: Boolean = false,
    val le: String? = null,
) {
    @Serializable
    data class Cible(val ecran: String? = null, val reference: String? = null, val id: Long? = null)
}

/** GET /notifications/compteur : léger, interrogé souvent. Réponse enveloppée : { data: { non_lues, par_categorie, derniere } }. */
@Serializable
data class ReponseCompteur(val data: CompteurNotifications = CompteurNotifications())

@Serializable
data class CompteurNotifications(
    @SerialName("non_lues") val nonLues: Int = 0,
    @SerialName("par_categorie") val parCategorie: Map<String, Int> = emptyMap(),
    val derniere: Notification? = null,
)

/** POST /appareils : jeton Firebase enregistré ; push_actif = Firebase configuré sur la plateforme. */
@Serializable
data class Appareil(val data: Donnees) {
    @Serializable
    data class Donnees(val id: Long = 0, @SerialName("push_actif") val pushActif: Boolean = false)
}

@Serializable
data class Parrainage(
    val actif: Boolean = false,
    val code: String? = null,
    val lien: String? = null,
    @SerialName("message_partage") val messagePartage: String? = null,
    @SerialName("bonus_parrain") val bonusParrain: Int = 0,
    @SerialName("bonus_filleul") val bonusFilleul: Int = 0,
    val disponible: Int = 0,
    val filleuls: List<Filleul> = emptyList(),
) {
    @Serializable
    data class Filleul(val prenom: String = "", @SerialName("inscrit_le") val inscritLe: String? = null, val recompense: Boolean = false)
}

/* ---------- Configuration ---------- */

@Serializable
data class Configuration(
    @SerialName("version_minimale") val versionMinimale: String = "1.0.0",
    val villes: List<String> = emptyList(),
    val formules: List<FormuleConfig> = emptyList(),
    /** « logement » et « salle » (salles de fêtes ou de conférence). */
    val categories: List<Enumeration> = emptyList(),
    @SerialName("types_logement") val typesLogement: List<Enumeration> = emptyList(),
    /** « proprietaire » (il gère) ou « vivi » (gestion confiée à VIVI), avec le texte du choix et son aide. */
    @SerialName("modes_gestion") val modesGestion: List<ModeGestion> = emptyList(),
    val equipements: List<Equipement> = emptyList(),
    val services: List<Service> = emptyList(),
    val connexion: CanauxConnexion = CanauxConnexion(),
    /** Notifications push (Firebase) configurées sur la plateforme. */
    val push: Boolean = false,
    /** Publicité active : l'application appelle GET /publicites. */
    val publicite: Boolean = false,
    /** Commission des logements ; celle des salles dépend du montant (voir [commissionSallesTranches]). */
    @SerialName("commission_pourcentage") val commissionPourcentage: Double = 0.0,
    @SerialName("commission_salles_tranches") val commissionSallesTranches: List<Tranche> = emptyList(),
    @SerialName("report_delai_minimum_heures") val reportDelaiHeures: Int = 3,
    @SerialName("report_fenetre_jours") val reportFenetreJours: Int = 10,
    @SerialName("blocage_creneau_minutes") val blocageMinutes: Int = 15,
    val parrainage: ParrainageConfig = ParrainageConfig(),
    val contact: Contact = Contact(),
    val pages: Pages = Pages(),
) {
    @Serializable
    data class ModeGestion(val valeur: String = "", val libelle: String = "", val choix: String = "", val aide: String = "")

    /** Taux d'une tranche de montant (location + services) ; jusqua = null : au-delà. */
    @Serializable
    data class Tranche(val jusqua: Int? = null, val pourcentage: Double = 0.0)

    @Serializable
    data class FormuleConfig(
        val valeur: String = "", val libelle: String = "", val court: String = "", val unite: String = "",
        val budget: Budget? = null,
        /** Fourchette de prix des salles (plus chères que les logements). */
        @SerialName("budget_salle") val budgetSalle: Budget? = null,
    ) {
        fun budgetPour(salle: Boolean) = if (salle) budgetSalle ?: budget else budget
    }

    @Serializable
    data class Budget(val min: Int = 0, val max: Int = 0, val pas: Int = 1)

    @Serializable
    data class CanauxConnexion(
        val email: Boolean = true,
        val sms: Boolean = false,
        val google: Boolean = false,                                   // bouton « Continuer avec Google » (clients seulement)
        @SerialName("google_client_id") val googleClientId: String? = null,   // identifiant client Web donné à Google Sign-In
    )

    @Serializable
    data class ParrainageConfig(val actif: Boolean = false, @SerialName("bonus_filleul") val bonusFilleul: Int = 0)

    @Serializable
    data class Contact(val whatsapp: String? = null, val email: String? = null)

    @Serializable
    data class Pages(
        val conditions: String? = null,
        val annulation: String? = null,
        @SerialName("suppression_compte") val suppressionCompte: String? = null,
        /** null tant que la politique n'est pas publiée : le lien est alors masqué. */
        val confidentialite: String? = null,
    )
}

@Serializable
data class Equipement(val id: Long, val nom: String = "", val icone: String? = null)

@Serializable
data class Service(
    val id: Long,
    val nom: String = "",
    val description: String? = null,
    val prix: Int = 0,
    val unite: String? = null,
    val icone: String? = null,
    val quantite: Int = 0,
)

/* ---------- Logements (client) ---------- */

@Serializable
data class Logement(
    val id: Long = 0,
    val slug: String = "",
    val titre: String = "",
    val type: Enumeration? = null,
    /** « logement » ou « salle » (salle de fêtes ou de conférence : capacité en personnes, pas de chambres ni de lits). */
    val categorie: String = "logement",
    @SerialName("est_salle") val estSalle: Boolean = false,
    /** « 300 places » ou « 2 voyageurs », prêt à afficher. */
    @SerialName("capacite_libelle") val capaciteLibelle: String? = null,
    val ville: String = "",
    val quartier: String? = null,
    val capacite: Int = 0,
    val chambres: Int = 0,
    val lits: Int = 0,
    @SerialName("salles_de_bain") val sallesDeBain: Int = 0,
    val unites: Int = 1,
    /** Réservable à l'heure de jour comme de nuit (appartement ou salle). */
    @SerialName("ouvert_24h") val ouvert24h: Boolean = false,
    val note: Double? = null,
    @SerialName("nombre_avis") val nombreAvis: Int = 0,
    val photo: String? = null,
    @SerialName("a_partir_de") val aPartirDe: APartirDe? = null,
    @SerialName("distance_km") val distanceKm: Double? = null,
    val zone: Zone? = null,
    val description: String? = null,
    @SerialName("temps_preparation_minutes") val tempsPreparation: Int? = null,
    val photos: List<Photo> = emptyList(),
    val equipements: List<Equipement> = emptyList(),
    val services: List<Service> = emptyList(),
    val tarifs: List<Tarif> = emptyList(),
) {
    @Serializable
    data class APartirDe(val formule: Enumeration? = null, val prix: Int = 0, @SerialName("prix_texte") val prixTexte: String = "", val unite: String = "")

    @Serializable
    data class Zone(val latitude: Double, val longitude: Double, @SerialName("rayon_m") val rayonM: Int = 400)

    @Serializable
    data class Tarif(val formule: Enumeration, val prix: Int = 0, @SerialName("prix_texte") val prixTexte: String = "", val unite: String = "")
}

@Serializable
data class Photo(val id: Long = 0, val url: String? = null, val legende: String? = null, val ordre: Int = 0)

@Serializable
data class Recherche(val data: List<Logement> = emptyList(), val meta: Meta? = null, val criteres: Criteres? = null) {
    @Serializable
    data class Criteres(
        val ville: String? = null,
        val date: String? = null,
        val voyageurs: Int = 1,
        val formule: String? = null,
        val tri: String? = null,
        @SerialName("hors_rayon") val horsRayon: Boolean = false,
        @SerialName("rayon_km") val rayonKm: Double? = null,
    )
}

@Serializable
data class FicheLogement(val data: Logement, val apercu: Boolean = false)

@Serializable
data class Avis(val note: Int = 0, val commentaire: String? = null, val auteur: String? = null, val le: String? = null)

/** Bloc « Réserver », recalculé par le serveur à chaque changement (App\Services\SimulationSejour). */
@Serializable
data class Disponibilites(
    val date: String = "",
    @SerialName("date_min") val dateMin: String = "",
    @SerialName("date_texte") val dateTexte: String = "",
    val voyageurs: Int = 1,
    val capacite: Int = 1,
    val formule: String? = null,
    val formules: List<Formule> = emptyList(),
    val heures: List<Heure> = emptyList(),
    val heure: String? = null,
    val duree: Int? = null,
    @SerialName("duree_min") val dureeMin: Int? = null,
    @SerialName("duree_max") val dureeMax: Int? = null,
    val creneaux: List<Creneau> = emptyList(),
    @SerialName("creneau_id") val creneauId: Long? = null,
    val jours: Int? = null,
    @SerialName("jours_max") val joursMax: Int? = null,
    val aide: String? = null,
    val devis: Devis? = null,
    val message: String? = null,
) {
    @Serializable
    data class Formule(
        val valeur: String,
        val court: String = "",
        val libelle: String = "",
        val prix: Int = 0,
        @SerialName("prix_texte") val prixTexte: String = "",
        val unite: String = "",
    )

    @Serializable
    data class Heure(val heure: String, @SerialName("duree_max") val dureeMax: Int = 0, val libre: Boolean = false)

    @Serializable
    data class Creneau(val id: Long, val nom: String = "", val de: String = "", val a: String = "", val libre: Boolean = false)
}

@Serializable
data class Devis(
    val formule: String? = null,
    val debut: String? = null,
    val fin: String? = null,
    val location: Int = 0,
    val services: Int = 0,
    @SerialName("lignes_services") val lignesServices: List<LigneService> = emptyList(),
    @SerialName("taux_commission") val tauxCommission: Double = 0.0,
    val commission: Int = 0,
    val total: Int = 0,
    val bonus: Int = 0,
    @SerialName("remise_agent") val remiseAgent: Int = 0,
    @SerialName("a_payer") val aPayer: Int = 0,
) {
    @Serializable
    data class LigneService(
        @SerialName("service_id") val serviceId: Long = 0,
        val nom: String = "",
        val quantite: Int = 0,
        @SerialName("prix_unitaire") val prixUnitaire: Int = 0,
        val montant: Int = 0,
    )
}

/* ---------- Réservations ---------- */

@Serializable
data class Reservation(
    val reference: String = "",
    val statut: Enumeration = Enumeration(),
    val formule: Enumeration = Enumeration(),
    val debut: String? = null,
    val fin: String? = null,
    val creneau: String = "",
    val duree: String = "",
    val voyageurs: Int = 1,
    /** « 2 voyageurs », ou null pour une salle (réservée entière) : c'est ce texte qui s'affiche, pas [voyageurs]. */
    val personnes: String? = null,
    val logement: LogementReserve? = null,
    val montants: Montants = Montants(),
    val services: List<Devis.LigneService> = emptyList(),
    @SerialName("retenu_jusqua") val retenuJusqua: String? = null,
    @SerialName("nombre_reports") val nombreReports: Int = 0,
    @SerialName("annulee_le") val annuleeLe: String? = null,
    @SerialName("scanne_le") val scanneLe: String? = null,
    // Vue client
    val billet: Billet? = null,
    val actions: Actions = Actions(),
    val avis: AvisDonne? = null,
    @SerialName("delai_report_heures") val delaiReportHeures: Int = 3,
    @SerialName("moyen_paiement") val moyenPaiement: String? = null,
    // Tunnel (en attente de paiement)
    @SerialName("services_proposes") val servicesProposes: List<Service> = emptyList(),
    val devis: Devis? = null,
    val bonus: Bonus? = null,
    val coordonnees: Coordonnees? = null,
    // Vue propriétaire / gestionnaire
    val client: Client? = null,
    val origine: String? = null,
    @SerialName("credit_proprietaire") val creditProprietaire: Int = 0,
    /** Retirés de la location et déjà déduits de [creditProprietaire] : part marketing, rémunération de VIVI. */
    @SerialName("part_marketing") val partMarketing: Int = 0,
    @SerialName("part_gestion") val partGestion: Int = 0,
    /** Agent marketing à qui la part est versée ; null : elle revient à VIVI. */
    val agent: AgentVenteur? = null,
    @SerialName("montant_encaisse") val montantEncaisse: Int = 0,
    @SerialName("reste_a_payer") val resteAPayer: Int = 0,
    @SerialName("note_interne") val noteInterne: String? = null,
    @SerialName("services_texte") val servicesTexte: String? = null,
) {
    @Serializable
    data class LogementReserve(
        val id: Long = 0,
        val slug: String = "",
        val titre: String = "",
        val ville: String = "",
        val quartier: String? = null,
        val photo: String? = null,
        val adresse: String? = null,
        val itineraire: String? = null,
        @SerialName("est_salle") val estSalle: Boolean = false,
    )

    @Serializable
    data class Montants(
        val location: Int = 0,
        val services: Int = 0,
        val commission: Int = 0,
        @SerialName("remise_agent") val remiseAgent: Int = 0,
        val bonus: Int = 0,
        val total: Int = 0,
        val paye: Int = 0,
        val rembourse: Int = 0,
    )

    @Serializable
    data class Billet(@SerialName("qr_contenu") val qrContenu: String, @SerialName("code_secours") val codeSecours: String)

    @Serializable
    data class Actions(
        val payer: Boolean = false,
        val reporter: Boolean = false,
        val annuler: Boolean = false,
        @SerialName("donner_avis") val donnerAvis: Boolean = false,
    )

    @Serializable
    data class AvisDonne(val note: Int = 0, val commentaire: String? = null, val statut: Enumeration? = null)

    @Serializable
    data class Bonus(val disponible: Int = 0, @SerialName("plafond_pourcentage") val plafondPourcentage: Int = 0)

    @Serializable
    data class Coordonnees(val nom: String? = null, val telephone: String? = null, val email: String? = null, val whatsapp: String? = null)

    @Serializable
    data class Client(
        val id: Long = 0, val nom: String = "", val telephone: String? = null,
        /** Numéro WhatsApp du client (tout pays) et lien https://wa.me/… pour lui écrire. */
        val whatsapp: String? = null,
        @SerialName("lien_whatsapp") val lienWhatsapp: String? = null,
    )

    @Serializable
    data class AgentVenteur(val id: Long = 0, val nom: String = "")

    val estManuelle get() = origine == "manuelle"
    val estSalle get() = logement?.estSalle == true

    /** « Studio Lagune · 2 voyageurs » ; pour une salle, seulement le texte donné. */
    fun avecPersonnes(texte: String) = listOfNotNull(texte.ifBlank { null }, personnes).joinToString(" · ")
}

@Serializable
data class EtatPaiement(
    val id: Long,
    val statut: Enumeration = Enumeration(),
    val termine: Boolean = false,
    val montant: Int = 0,
    val moyen: String? = null,
    @SerialName("paye_le") val payeLe: String? = null,
    val objet: Objet? = null,
    val formule: String? = null,
) {
    @Serializable
    data class Objet(val type: String = "", val reference: String? = null, val id: Long? = null)

    val reussi get() = statut.valeur == "reussi"
}

@Serializable
data class PaiementLance(@SerialName("url_paiement") val urlPaiement: String, val paiement: EtatPaiement)

@Serializable
data class OptionsReport(
    val possible: Boolean = false,
    val limite: String? = null,
    @SerialName("delai_minimum_heures") val delaiMinimumHeures: Int = 3,
    val jours: List<Jour> = emptyList(),
) {
    @Serializable
    data class Jour(val date: String, val options: List<Option> = emptyList())

    @Serializable
    data class Option(val valeur: String, val libelle: String = "", val debut: String? = null)
}

@Serializable
data class SimulationAnnulation(
    val politique: String = "",
    val regle: String = "",
    val paye: Int = 0,
    val commission: Int = 0,
    @SerialName("retenue_location") val retenueLocation: Int = 0,
    val retenue: Int = 0,
    val bonus: Int = 0,
    @SerialName("bonus_rendu") val bonusRendu: Int = 0,
    val rembourse: Int = 0,
    val moyen: String? = null,
)

/* ---------- Espace propriétaire / gestionnaire ---------- */

@Serializable
data class TableauDeBord(
    @SerialName("est_proprietaire") val estProprietaire: Boolean = false,
    @SerialName("arrivees_du_jour") val arriveesDuJour: List<Reservation> = emptyList(),
    val prochaines: List<Reservation> = emptyList(),
    val revenus: Revenus7j = Revenus7j(),
    val occupation: Occupation = Occupation(),
    @SerialName("logements_publies") val logementsPublies: Int = 0,
    val solde: Int? = null,
    val retraits: List<Retrait>? = null,
    val abonnement: AbonnementCourt? = null,
) {
    @Serializable
    data class Revenus7j(
        val mois: Int = 0,
        @SerialName("mois_precedent") val moisPrecedent: Int = 0,
        @SerialName("sept_jours") val septJours: List<Jour> = emptyList(),
    )

    @Serializable
    data class Jour(val jour: String, val montant: Int = 0)

    @Serializable
    data class Occupation(val mois: Int = 0, @SerialName("mois_precedent") val moisPrecedent: Int = 0)

    @Serializable
    data class AbonnementCourt(val formule: String = "", val fin: String? = null, val essai: Boolean = false)
}

@Serializable
data class Retrait(
    val reference: String = "",
    val montant: Int = 0,
    val statut: Enumeration = Enumeration(),
    @SerialName("mode_reglement") val modeReglement: String? = null,
    @SerialName("traite_le") val traiteLe: String? = null,
    /** Compte sur lequel la caissière paie (recopié au moment de la demande). */
    val compte: CompteRetrait.Compte? = null,
    val note: String? = null,
    val le: String? = null,
)

/** Réponse du scanner : 200 {valide: true, data} ou 422 {valide: false, titre, message}. */
@Serializable
data class VerificationArrivee(
    val valide: Boolean = false,
    val data: Reservation? = null,
    val titre: String? = null,
    val message: String? = null,
)

@Serializable
data class SejourDemarre(
    val reference: String = "",
    val heure: String? = null,
    val client: String = "",
    val logement: String = "",
    val credit: Int? = null,
)

@Serializable
data class LogementGere(
    val id: Long,
    val titre: String = "",
    val statut: Enumeration? = null,
    val unites: Int = 1,
    val photo: String? = null,
    val formules: List<Enumeration> = emptyList(),
    val creneaux: List<CreneauGere> = emptyList(),
    @SerialName("est_salle") val estSalle: Boolean = false,
    val capacite: Int = 0,
) {
    @Serializable
    data class CreneauGere(
        val id: Long,
        val nom: String = "",
        @SerialName("heure_debut") val heureDebut: String = "",
        @SerialName("heure_fin") val heureFin: String = "",
    )
}

@Serializable
data class Calendrier(
    val logement: LogementCal,
    val mois: String,
    val jours: List<JourCal> = emptyList(),
    val jour: DetailJour,
) {
    @Serializable
    data class LogementCal(val id: Long, val titre: String = "", val unites: Int = 1)

    @Serializable
    data class JourCal(val date: String, val etat: String = "libre", val passe: Boolean = false)

    @Serializable
    data class DetailJour(val date: String, val blocage: Blocage? = null, val reservations: List<Reservation> = emptyList())

    @Serializable
    data class Blocage(val id: Long, val motif: String? = null)
}

@Serializable
data class Revenus(
    val solde: Int = 0,
    @SerialName("ce_mois") val ceMois: Int = 0,
    @SerialName("retrait_minimum") val retraitMinimum: Int = 1000,
    val mouvements: List<Mouvement> = emptyList(),
    val retraits: List<Retrait> = emptyList(),
) {
    @Serializable
    data class Mouvement(
        val id: Long,
        val type: Enumeration? = null,
        val montant: Int = 0,
        val libelle: String? = null,
        val reservation: String? = null,
        val le: String? = null,
    )
}

@Serializable
data class LogementPro(
    val id: Long,
    val slug: String = "",
    val titre: String = "",
    val type: Enumeration? = null,
    val statut: Enumeration = Enumeration(),
    @SerialName("en_ligne") val enLigne: Boolean = false,
    val visible: Boolean = false,
    @SerialName("motif_refus") val motifRefus: String? = null,
    val ville: String = "",
    val quartier: String? = null,
    val capacite: Int = 0,
    @SerialName("est_salle") val estSalle: Boolean = false,
    @SerialName("capacite_libelle") val capaciteLibelle: String? = null,
    val unites: Int = 1,
    val photo: String? = null,
    @SerialName("mode_gestion") val modeGestion: Enumeration? = null,
    @SerialName("gerant_vivi") val gerantVivi: GerantVivi? = null,
) {
    val gereParVivi get() = modeGestion?.valeur == "vivi"
}

/** Gérant VIVI désigné par l'administration pour accueillir et scanner. */
@Serializable
data class GerantVivi(val id: Long = 0, val nom: String? = null)

/**
 * Fiche complète d'un logement vue par son propriétaire (GET /pro/logements/{id}) : mêmes noms de champs
 * que ceux attendus par POST et PUT, pour renvoyer le formulaire tel quel.
 */
@Serializable
data class FicheLogementPro(
    val id: Long = 0,
    val slug: String = "",
    val titre: String = "",
    val type: Enumeration? = null,
    val statut: Enumeration = Enumeration(),
    @SerialName("en_ligne") val enLigne: Boolean = false,
    @SerialName("motif_refus") val motifRefus: String? = null,
    val ville: String = "",
    val quartier: String? = null,
    val adresse: String? = null,
    val latitude: Double? = null,
    val longitude: Double? = null,
    val description: String? = null,
    val capacite: Int = 2,
    val chambres: Int = 1,
    val unites: Int = 1,
    val lits: Int = 1,
    @SerialName("salles_de_bain") val sallesDeBain: Int = 1,
    @SerialName("marketing_type") val marketingType: String? = null,
    @SerialName("marketing_valeur") val marketingValeur: Double? = null,
    @SerialName("temps_preparation_minutes") val tempsPreparation: Int = 60,
    /** Enumération {valeur, libelle} à la lecture ; seule la valeur est renvoyée à l'enregistrement. */
    @SerialName("mode_gestion") val modeGestion: Enumeration? = null,
    @SerialName("gerant_vivi") val gerantVivi: GerantVivi? = null,
    @SerialName("gestion_vivi_demandee_le") val gestionDemandeeLe: String? = null,
    /** Contrat fixé par l'administration (lecture seule). */
    @SerialName("gestion_remuneration") val gestionRemuneration: Remuneration? = null,
    val photos: List<Photo> = emptyList(),
    val tarifs: Tarifs = Tarifs(),
    val creneaux: List<Creneau> = emptyList(),
    val equipements: List<Long> = emptyList(),
    val services: List<ServiceChoisi> = emptyList(),
) {
    @Serializable
    data class Remuneration(
        val type: String = "", val valeur: Double = 0.0, val libelle: String = "",
        val contrat: String? = null, @SerialName("contrat_le") val contratLe: String? = null,
    )

    @Serializable
    data class Tarifs(
        val heure: TarifPro = TarifPro(),
        @SerialName("demi_journee") val demiJournee: TarifPro = TarifPro(),
        val journee: TarifPro = TarifPro(),
    )

    @Serializable
    data class TarifPro(
        val actif: Boolean = false,
        val prix: Int? = null,
        @SerialName("heure_ouverture") val heureOuverture: String? = null,
        @SerialName("heure_fermeture") val heureFermeture: String? = null,
        @SerialName("ouvert_24h") val ouvert24h: Boolean = false,
        @SerialName("duree_minimum_heures") val dureeMinimumHeures: Int? = null,
        @SerialName("heure_arrivee") val heureArrivee: String? = null,
        @SerialName("heure_depart") val heureDepart: String? = null,
    )

    @Serializable
    data class Creneau(val id: Long? = null, val nom: String = "", @SerialName("heure_debut") val heureDebut: String = "", @SerialName("heure_fin") val heureFin: String = "")

    @Serializable
    data class ServiceChoisi(val id: Long, val nom: String = "")
}

@Serializable
data class LogementsPro(val data: List<LogementPro> = emptyList(), val quota: Quota? = null) {
    @Serializable
    data class Quota(val utilises: Int = 0, val max: Int? = null, val formule: String? = null, val salles: Salles? = null)

    /** Les salles ont leur propre quota, séparé de celui des logements. */
    @Serializable
    data class Salles(val utilises: Int = 0, val max: Int? = null)
}

@Serializable
data class AbonnementPage(
    val actif: Abo? = null,
    val suivant: Abo? = null,
    @SerialName("logements_utilises") val logementsUtilises: Int = 0,
    val promotion: Promotion? = null,
    val essai: Essai = Essai(),
    val formules: List<FormuleAbo> = emptyList(),
    val paiements: List<EtatPaiement> = emptyList(),
) {
    @Serializable
    data class Abo(
        val id: Long,
        val formule: String = "",
        @SerialName("formule_id") val formuleId: Long? = null,
        val debut: String? = null,
        val fin: String? = null,
        val essai: Boolean = false,
        @SerialName("prix_paye") val prixPaye: Int = 0,
        val statut: Enumeration? = null,
    )

    @Serializable
    data class Promotion(val pourcentage: Int = 0, val libelle: String = "", val fin: String? = null)

    @Serializable
    data class Essai(val possible: Boolean = false, val jours: Int = 0, @SerialName("formule_id") val formuleId: Long? = null)

    @Serializable
    data class FormuleAbo(
        val id: Long,
        val nom: String = "",
        @SerialName("logements_max") val logementsMax: Int = 0,
        @SerialName("salles_max") val sallesMax: Int? = null,
        @SerialName("gestionnaires_max") val gestionnairesMax: Int = 0,
        val avantages: JsonElement? = null,
        val tarif: TarifAbo = TarifAbo(),
    )

    @Serializable
    data class TarifAbo(val prix: Int = 0, val remise: Int = 0, val net: Int = 0, val libelle: String? = null)
}

@Serializable
data class Gestionnaires(
    val maximum: Int = 0,
    val gestionnaires: List<Gestionnaire> = emptyList(),
    val logements: List<Ref> = emptyList(),
) {
    @Serializable
    data class Gestionnaire(
        val id: Long,
        val nom: String = "",
        val email: String? = null,
        val telephone: String? = null,
        val logements: List<Ref> = emptyList(),
    )

    @Serializable
    data class Ref(val id: Long, val titre: String = "")
}

/* ---------- Agent marketing ---------- */

/** Part de l'agent marketing sur un logement : pourcentage de la location ou montant fixe par réservation. */
@Serializable
data class PartMarketing(val type: String = "", val valeur: Double = 0.0, val libelle: String = "")

/** GET /agent : espace de l'agent marketing. */
@Serializable
data class EspaceAgent(
    @SerialName("code_agent") val codeAgent: String = "",
    val solde: Int = 0,
    @SerialName("ce_mois") val ceMois: Int = 0,
    @SerialName("total_gagne") val totalGagne: Int = 0,
    val sejours: Int = 0,
    @SerialName("a_venir") val aVenir: Int = 0,
    @SerialName("retrait_minimum") val retraitMinimum: Int = 1000,
    val logements: List<LogementPromu> = emptyList(),
    val mouvements: List<Revenus.Mouvement> = emptyList(),
    val retraits: List<Retrait> = emptyList(),
) {
    @Serializable
    data class LogementPromu(
        val id: Long,
        val slug: String = "",
        val titre: String = "",
        val quartier: String? = null,
        val ville: String = "",
        val photo: String? = null,
        val proprietaire: String? = null,
        @SerialName("en_ligne") val enLigne: Boolean = false,
        val lien: String = "",
        val part: PartMarketing? = null,
        val sejours: Int = 0,
        @SerialName("a_venir") val aVenir: Int = 0,
        val gains: Int = 0,
    )
}

/** GET /pro/agents : classement des agents du propriétaire et part marketing de chaque logement. */
@Serializable
data class AgentsPage(
    val periode: String = "mois",
    val classement: List<Ligne> = emptyList(),
    val logements: List<LogementPart> = emptyList(),
) {
    @Serializable
    data class Ligne(
        val rang: Int = 0,
        val agent: Agent,
        val sejours: Int = 0,
        @SerialName("a_venir") val aVenir: Int = 0,
        @SerialName("chiffre_location") val chiffreLocation: Int = 0,
        val gains: Int = 0,
        val logements: List<Ref> = emptyList(),
    )

    @Serializable
    data class Agent(val id: Long, val nom: String = "", val telephone: String? = null, val email: String? = null)

    @Serializable
    data class Ref(val id: Long, val titre: String = "")

    @Serializable
    data class LogementPart(val id: Long, val titre: String = "", val part: PartMarketing? = null)
}

/** GET · PUT /compte-retrait : numéro sur lequel la caissière VIVI paie les retraits (propriétaire, agent). */
@Serializable
data class CompteRetrait(
    val moyens: List<String> = emptyList(),
    val configure: Boolean = false,
    val moyen: String? = null,
    val numero: String? = null,
    val titulaire: String? = null,
    @SerialName("modifie_le") val modifieLe: String? = null,
) {
    @Serializable
    data class Compte(val moyen: String? = null, val numero: String? = null, val titulaire: String? = null)

    /** « MTN Mobile Money · 01 97 00 00 01 » */
    val resume get() = listOfNotNull(moyen, numero?.let(::numeroLisible)).joinToString(" · ")
}

/** « 0197000001 » → « 01 97 00 00 01 » ; un RIB reste tel quel. */
fun numeroLisible(n: String): String = if (n.length == 10 && n.all(Char::isDigit)) n.chunked(2).joinToString(" ") else n
