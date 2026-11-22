package bj.vivi.app.data

import android.content.Context
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/** GET /publicites : espaces publicitaires de l'application. Contrat : docs/API-mobile.md § Publicité. */
@Serializable
data class Publicites(
    val active: Boolean = false,
    /** Texte toujours affiché sur une annonce de VIVI (« Publicité »). */
    val mention: String = "Publicité",
    @SerialName("frequence_liste") val frequenceListe: Int = 6,
    @SerialName("plein_ecran_heures") val pleinEcranHeures: Int = 24,
    @SerialName("admob_test") val admobTest: Boolean = false,
    val emplacements: Map<String, Emplacement> = emptyMap(),
) {
    @Serializable
    data class Emplacement(
        val libelle: String = "",
        val format: String = "banniere",
        val largeur: Int = 1200,
        val hauteur: Int = 400,
        val annonces: List<Annonce> = emptyList(),
        val admob: Bloc? = null,
    ) {
        val proportion get() = if (largeur > 0 && hauteur > 0) largeur.toFloat() / hauteur else 3f
    }

    @Serializable
    data class Annonce(
        val id: Long = 0,
        val titre: String = "",
        val annonceur: String? = null,
        val texte: String? = null,
        val bouton: String? = null,
        val image: String? = null,
        /** null : l'annonce ne s'ouvre pas. */
        val lien: String? = null,
        @SerialName("lien_interne") val lienInterne: Boolean = false,
    )

    /** Bloc d'annonces Google AdMob ; format : « banniere_adaptative », « natif » ou « ouverture ». */
    @Serializable
    data class Bloc(val format: String = "", val bloc: String = "")

    val avecAdmob get() = active && emplacements.values.any { it.admob != null }
}

/**
 * Publicités de l'application : chargement, règles d'affichage, vues et clics.
 * Jamais dans les espaces propriétaire, gestionnaire et agent (voir [interdite]).
 */
object Pub {
    const val DEMARRAGE = "demarrage"
    const val ACCUEIL_HAUT = "accueil_haut"
    const val ACCUEIL_MILIEU = "accueil_milieu"
    const val RECHERCHE = "recherche"
    const val FICHE = "fiche"
    const val RESERVATIONS = "reservations"

    private const val PREFS = "vivi_pub"
    private const val CLE_DEJA_OUVERTE = "deja_ouverte"
    private const val CLE_PLEIN_ECRAN = "plein_ecran_le"
    private const val RELECTURE_MS = 10 * 60_000L

    var donnees by mutableStateOf<Publicites?>(null)
        private set

    /** Animation d'ouverture, verrou ou annonce plein écran par-dessus : les vues ne comptent pas. */
    var masquee by mutableStateOf(true)

    private val portee = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private var ville: String? = null
    private var chargeeLe = 0L

    fun interdite(u: Utilisateur?) = u != null && (u.estPro || u.estEspaceAgent)

    /** Annonces pour la ville recherchée (null : toutes). Relues si la ville change ou après 10 min. */
    suspend fun charger(villeVoulue: String?) {
        if (Catalogue.obtenir()?.publicite != true || interdite(Reseau.session.utilisateur.value)) return
        val maintenant = System.currentTimeMillis()
        if (villeVoulue == ville && maintenant - chargeeLe < RELECTURE_MS) return
        appel { publicites(villeVoulue) }.onSuccess {
            ville = villeVoulue
            chargeeLe = maintenant
            donnees = it.data
        }
    }

    /** Emplacement à afficher ; null : l'espace disparaît. */
    fun emplacement(cle: String): Publicites.Emplacement? =
        donnees?.takeIf { it.active }?.emplacements?.get(cle)?.takeIf { it.annonces.isNotEmpty() || it.admob != null }

    // Une seule tentative : pas de nouvel essai en cas d'erreur
    fun vue(id: Long) { portee.launch { appel { vuePublicite(id) } } }
    fun clic(id: Long) { portee.launch { appel { clicPublicite(id) } } }

    /* ---------- Plein écran d'ouverture ---------- */

    /** L'application a-t-elle déjà été ouverte ? (La toute première ouverture n'a jamais de publicité plein écran.) */
    fun dejaOuverte(context: Context): Boolean {
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val deja = prefs.getBoolean(CLE_DEJA_OUVERTE, false)
        if (!deja) prefs.edit().putBoolean(CLE_DEJA_OUVERTE, true).apply()
        return deja
    }

    fun delaiEcoule(context: Context, heures: Int): Boolean {
        val dernier = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getLong(CLE_PLEIN_ECRAN, 0)
        val ecoule = System.currentTimeMillis() - dernier
        return ecoule < 0 || ecoule >= heures * 3_600_000L   // < 0 : horloge du téléphone reculée
    }

    fun marquerPleinEcran(context: Context) =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putLong(CLE_PLEIN_ECRAN, System.currentTimeMillis()).apply()
}
