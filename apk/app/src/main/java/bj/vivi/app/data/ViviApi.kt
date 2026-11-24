package bj.vivi.app.data

import kotlinx.serialization.json.JsonObject
import okhttp3.MultipartBody
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.Multipart
import retrofit2.http.Part
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.PATCH
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Path
import retrofit2.http.Query
import retrofit2.http.QueryMap

/**
 * Routes de l'API mobile (plateforme/routes/api.php, préfixe /api/v1).
 * Les corps sont envoyés en JSON ; voir Corps.kt pour les petits constructeurs.
 */
interface ViviApi {

    /* ---------- Sans connexion ---------- */

    @GET("configuration")
    suspend fun configuration(): Enveloppe<Configuration>

    @POST("auth/code")
    suspend fun demanderCode(@Body corps: JsonObject): DemandeCode

    @POST("auth/renvoyer")
    suspend fun renvoyerCode(@Body corps: JsonObject): Renvoi

    @POST("auth/verifier")
    suspend fun verifierCode(@Body corps: JsonObject): Connexion

    /** Connexion ou création d'un compte CLIENT avec Google { id_token, appareil?, invitation? }. */
    @POST("auth/google")
    suspend fun connexionGoogle(@Body corps: JsonObject): Connexion

    /** Espaces publicitaires (sans connexion) ; seulement si configuration.publicite. */
    @GET("publicites")
    suspend fun publicites(@Query("ville") ville: String?): Enveloppe<Publicites>

    @POST("publicites/{id}/vue")
    suspend fun vuePublicite(@Path("id") id: Long): Response<Unit>

    @POST("publicites/{id}/clic")
    suspend fun clicPublicite(@Path("id") id: Long): Response<Unit>

    @GET("logements")
    suspend fun rechercher(
        @Query("ville") ville: String?,
        @Query("date") date: String?,
        @Query("voyageurs") voyageurs: Int?,
        @Query("formule") formule: String?,
        @Query("categorie") categorie: String?,
        @Query("budget") budget: Int?,
        @Query("tri") tri: String?,
        @Query("type[]") types: List<String>,
        @Query("equipement[]") equipements: List<Long>,
        @Query("lat") lat: Double?,
        @Query("lng") lng: Double?,
        @Query("page") page: Int,
    ): Recherche

    @GET("logements/{slug}")
    suspend fun logement(@Path("slug") slug: String): FicheLogement

    @GET("logements/{slug}/disponibilites")
    suspend fun disponibilites(@Path("slug") slug: String, @QueryMap demande: Map<String, String>): Enveloppe<Disponibilites>

    @GET("logements/{slug}/avis")
    suspend fun avis(@Path("slug") slug: String, @Query("page") page: Int = 1): Liste<Avis>

    /* ---------- Compte ---------- */

    @GET("moi")
    suspend fun moi(): Enveloppe<Utilisateur>

    /** { jeton_push? } : ce téléphone ne reçoit plus les notifications push de ce compte. */
    @POST("auth/deconnexion")
    suspend fun deconnexion(@Body corps: JsonObject = JsonObject(emptyMap())): Message

    /** Jeton Firebase (FCM) du téléphone { jeton, plateforme: android, nom }. */
    @POST("appareils")
    suspend fun enregistrerAppareil(@Body corps: JsonObject): Appareil

    @GET("notifications/compteur")
    suspend fun compteurNotifications(): ReponseCompteur

    /** { recevoir_offres: bool } */
    @PUT("profil/preferences")
    suspend fun preferences(@Body corps: JsonObject): Profil

    @POST("auth/deconnexion-partout")
    suspend fun deconnexionPartout(): Message

    @GET("profil")
    suspend fun profil(): Profil

    @PUT("profil/nom")
    suspend fun changerNom(@Body corps: JsonObject): Enveloppe<Utilisateur>

    @POST("profil/changement")
    suspend fun demanderChangement(@Body corps: JsonObject): ChangementDemande

    @POST("profil/changement/confirmer")
    suspend fun confirmerChangement(@Body corps: JsonObject): Enveloppe<Utilisateur>

    /** Suppression du compte : ce qui l'empêche, ce qui est perdu, où part le code. */
    @GET("compte/suppression")
    suspend fun suppressionCompte(): Enveloppe<SuppressionCompte>

    @POST("compte/suppression/code")
    suspend fun codeSuppression(): ChangementDemande

    /** { code } : supprime le compte ; le jeton d'accès ne fonctionne plus ensuite (401). */
    @POST("compte/suppression")
    suspend fun supprimerCompte(@Body corps: JsonObject): Message

    @GET("notifications")
    suspend fun notifications(@Query("page") page: Int = 1, @Query("categorie") categorie: String? = null): Liste<Notification>

    @DELETE("notifications/{id}")
    suspend fun supprimerNotification(@Path("id") id: String): Message

    @POST("notifications/{id}/lu")
    suspend fun lireNotification(@Path("id") id: String): Message

    @POST("notifications/tout-lu")
    suspend fun toutLire(): Message

    @GET("parrainage")
    suspend fun parrainage(): Enveloppe<Parrainage>

    @GET("paiements/{id}")
    suspend fun paiement(@Path("id") id: Long): Enveloppe<EtatPaiement>

    /* ---------- Client ---------- */

    @POST("logements/{slug}/reserver")
    suspend fun reserver(@Path("slug") slug: String, @Body corps: JsonObject): Enveloppe<Reservation>

    @GET("reservations")
    suspend fun mesReservations(@Query("onglet") onglet: String): Liste<Reservation>

    @GET("reservations/{ref}")
    suspend fun reservation(@Path("ref") reference: String): Enveloppe<Reservation>

    @POST("reservations/{ref}/devis")
    suspend fun devis(@Path("ref") reference: String, @Body corps: JsonObject): Enveloppe<Devis>

    @POST("reservations/{ref}/payer")
    suspend fun payer(@Path("ref") reference: String, @Body corps: JsonObject): Enveloppe<PaiementLance>

    @GET("reservations/{ref}/report")
    suspend fun optionsReport(@Path("ref") reference: String): Enveloppe<OptionsReport>

    @POST("reservations/{ref}/report")
    suspend fun reporter(@Path("ref") reference: String, @Body corps: JsonObject): Enveloppe<Reservation>

    @GET("reservations/{ref}/annulation")
    suspend fun simulationAnnulation(@Path("ref") reference: String): Enveloppe<SimulationAnnulation>

    @POST("reservations/{ref}/annuler")
    suspend fun annuler(@Path("ref") reference: String, @Body corps: JsonObject): Enveloppe<Reservation>

    @POST("reservations/{ref}/avis")
    suspend fun donnerAvis(@Path("ref") reference: String, @Body corps: JsonObject): Message

    /* ---------- Propriétaire et gestionnaire ---------- */

    @GET("pro/tableau-de-bord")
    suspend fun tableauDeBord(): Enveloppe<TableauDeBord>

    @GET("pro/arrivees")
    suspend fun arrivees(): Liste<Reservation>

    @POST("pro/scanner/verifier")
    suspend fun verifierArrivee(@Body corps: JsonObject): VerificationArrivee

    @POST("pro/scanner/demarrer")
    suspend fun demarrerSejour(@Body corps: JsonObject): Enveloppe<SejourDemarre>

    @GET("pro/logements-geres")
    suspend fun logementsGeres(): Liste<LogementGere>

    @GET("pro/reservations")
    suspend fun reservationsPro(@Query("onglet") onglet: String, @Query("logement") logement: Long? = null, @Query("page") page: Int = 1): Liste<Reservation>

    @GET("pro/reservations/{ref}")
    suspend fun reservationPro(@Path("ref") reference: String): Enveloppe<Reservation>

    @GET("pro/reservations/{ref}/deplacement")
    suspend fun optionsDeplacement(@Path("ref") reference: String, @Query("date") date: String? = null): Enveloppe<OptionsDeplacement>

    @POST("pro/reservations/{ref}/deplacer")
    suspend fun deplacer(@Path("ref") reference: String, @Body corps: JsonObject): Enveloppe<Reservation>

    @POST("pro/reservations/{ref}/encaisser")
    suspend fun encaisser(@Path("ref") reference: String, @Body corps: JsonObject): Enveloppe<Reservation>

    @POST("pro/reservations/{ref}/retirer")
    suspend fun retirer(@Path("ref") reference: String): Enveloppe<Reservation>

    @GET("pro/logements/{id}/calendrier")
    suspend fun calendrier(@Path("id") logement: Long, @Query("mois") mois: String?, @Query("jour") jour: String?): Enveloppe<Calendrier>

    @POST("pro/logements/{id}/blocages")
    suspend fun bloquer(@Path("id") logement: Long, @Body corps: JsonObject): Message

    @DELETE("pro/logements/{id}/blocages/{blocage}")
    suspend fun debloquer(@Path("id") logement: Long, @Path("blocage") blocage: Long): Message

    @POST("pro/logements/{id}/reservations")
    suspend fun ajouterReservation(@Path("id") logement: Long, @Body corps: JsonObject): Enveloppe<Reservation>

    /* ---------- Propriétaire seulement ---------- */

    @GET("pro/logements")
    suspend fun mesLogements(): LogementsPro

    @GET("pro/logements/{id}")
    suspend fun ficheLogementPro(@Path("id") logement: Long): Enveloppe<FicheLogementPro>

    @POST("pro/logements")
    suspend fun creerLogement(@Body corps: JsonObject): Enveloppe<FicheLogementPro>

    @PUT("pro/logements/{id}")
    suspend fun modifierLogement(@Path("id") logement: Long, @Body corps: JsonObject): Enveloppe<FicheLogementPro>

    @Multipart
    @POST("pro/logements/{id}/photos")
    suspend fun ajouterPhotos(@Path("id") logement: Long, @Part photos: List<MultipartBody.Part>): Enveloppe<FicheLogementPro>

    @DELETE("pro/logements/{id}/photos/{photo}")
    suspend fun supprimerPhoto(@Path("id") logement: Long, @Path("photo") photo: Long): Enveloppe<FicheLogementPro>

    @POST("pro/logements/{id}/photos/{photo}/principale")
    suspend fun photoPrincipale(@Path("id") logement: Long, @Path("photo") photo: Long): Enveloppe<FicheLogementPro>

    @GET("pro/agents")
    suspend fun agentsPro(@Query("periode") periode: String): Enveloppe<AgentsPage>

    @POST("pro/agents")
    suspend fun designerAgent(@Body corps: JsonObject): Message

    @PUT("pro/agents/{id}")
    suspend fun modifierAgent(@Path("id") agent: Long, @Body corps: JsonObject): Message

    @PATCH("pro/logements/{id}/en-ligne")
    suspend fun mettreEnLigne(@Path("id") logement: Long, @Body corps: JsonObject): Enveloppe<LogementPro>

    @GET("pro/revenus")
    suspend fun revenus(): Enveloppe<Revenus>

    @POST("pro/retraits")
    suspend fun demanderRetrait(@Body corps: JsonObject): Enveloppe<Retrait>

    @GET("pro/abonnement")
    suspend fun abonnement(): Enveloppe<AbonnementPage>

    @POST("pro/abonnement/{formule}")
    suspend fun souscrire(@Path("formule") formule: Long): Enveloppe<PaiementLance>

    @POST("pro/abonnement/{formule}/essai")
    suspend fun essai(@Path("formule") formule: Long): Message

    @GET("pro/gestionnaires")
    suspend fun gestionnaires(): Enveloppe<Gestionnaires>

    @POST("pro/gestionnaires")
    suspend fun confierGestion(@Body corps: JsonObject): Message

    @PUT("pro/gestionnaires/{id}")
    suspend fun modifierGestionnaire(@Path("id") id: Long, @Body corps: JsonObject): Message

    /* ---------- Compte de retrait (propriétaire, agent marketing) ---------- */

    @GET("compte-retrait")
    suspend fun compteRetrait(): Enveloppe<CompteRetrait>

    @PUT("compte-retrait")
    suspend fun enregistrerCompteRetrait(@Body corps: JsonObject): Enveloppe<CompteRetrait>

    /* ---------- Agent marketing ---------- */

    @GET("agent")
    suspend fun espaceAgent(): Enveloppe<EspaceAgent>

    @POST("agent/retraits")
    suspend fun retraitAgent(@Body corps: JsonObject): Enveloppe<Retrait>
}
