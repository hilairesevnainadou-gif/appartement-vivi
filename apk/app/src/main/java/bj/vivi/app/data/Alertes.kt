package bj.vivi.app.data

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.compose.ui.graphics.toArgb
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import bj.vivi.app.MainActivity
import bj.vivi.app.R
import bj.vivi.app.ui.theme.Vivi
import java.util.concurrent.TimeUnit

/**
 * Notifications du téléphone (barre de notifications d'Android). Contrat : docs/API-mobile.md § Notifications / Push.
 *
 * Deux voies, sans doublon :
 *  - push Firebase (instantané, application fermée) quand la plateforme et le téléphone l'ont (voir [Push]) ;
 *  - sinon, vérification : toutes les 10 s application ouverte, toutes les 15 min en arrière-plan (WorkManager).
 *    GET /notifications/compteur (léger) ; la liste n'est relue que si une nouvelle notification est arrivée.
 * Un appui ouvre l'écran visé (cible.ecran : billet, réservation pro, parrainage, solde…) et marque la notification lue.
 */
object Alertes {
    /**
     * Même canal que les push de la plateforme (android.notification.channel_id = "vivi_alertes").
     * Nouveau nom : l'ancien canal « vivi » ne vibrait pas, et Android ne modifie plus un canal déjà créé.
     */
    const val CANAL = "vivi_alertes"

    // Clés des « extras » de l'ouverture : les mêmes que les données (data) du push Firebase
    const val EXTRA_ID = "notification_id"
    const val EXTRA_ECRAN = "ecran"
    const val EXTRA_REFERENCE = "reference"
    const val EXTRA_CIBLE_ID = "id"
    const val EXTRA_LIEN = "lien"

    private const val TRAVAIL = "vivi_verification_notifications"
    private const val PREFS = "vivi_alertes"
    private const val CLE_VUES = "vues"
    private const val CLE_DERNIERE = "derniere"
    private const val CLE_INITIALISE = "initialise"

    fun creerCanal(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val nm = context.getSystemService(NotificationManager::class.java) ?: return
        nm.deleteNotificationChannel("vivi_notifications")   // anciens noms (versions de test précédentes)
        nm.deleteNotificationChannel("vivi")
        nm.createNotificationChannel(
            NotificationChannel(CANAL, "Réservations et offres", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Confirmation, séjour commencé, rappels, paiements, retraits et offres de VIVI"
                setSound(
                    android.media.RingtoneManager.getDefaultUri(android.media.RingtoneManager.TYPE_NOTIFICATION),
                    android.media.AudioAttributes.Builder()
                        .setUsage(android.media.AudioAttributes.USAGE_NOTIFICATION)
                        .setContentType(android.media.AudioAttributes.CONTENT_TYPE_SONIFICATION)
                        .build(),
                )
                enableVibration(true)
                vibrationPattern = longArrayOf(0, 250, 150, 250)
                enableLights(true)
            },
        )
    }

    /** Vérification périodique en arrière-plan (réseau requis). */
    fun planifier(context: Context) {
        val travail = PeriodicWorkRequestBuilder<TravailNotifications>(15, TimeUnit.MINUTES)
            .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
            .build()
        WorkManager.getInstance(context).enqueueUniquePeriodicWork(TRAVAIL, ExistingPeriodicWorkPolicy.KEEP, travail)
    }

    fun autorisees(context: Context): Boolean =
        (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED) &&
            NotificationManagerCompat.from(context).areNotificationsEnabled()

    /**
     * Met à jour la pastille et affiche les nouvelles notifications. Renvoie le nombre de non lues,
     * ou null sans session / sans réseau. Au premier passage, seules les 3 non lues les plus récentes sont montrées.
     * Push actif : les notifications arrivent déjà par Firebase, on ne les affiche pas une seconde fois.
     */
    suspend fun verifier(context: Context): Int? {
        if (Reseau.session.jeton == null) return null
        val compteur = appel { compteurNotifications() }.getOrNull()?.data ?: return null
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val premierPassage = !prefs.getBoolean(CLE_INITIALISE, false)
        val derniere = compteur.derniere?.id
        if (!premierPassage && derniere == prefs.getString(CLE_DERNIERE, null)) return compteur.nonLues

        val liste = appel { notifications() }.getOrNull() ?: return compteur.nonLues
        val vues = prefs.getStringSet(CLE_VUES, emptySet())!!.toMutableSet()
        val nouvelles = liste.data.filter { !it.lue && it.id !in vues }
        val aMontrer = if (premierPassage) nouvelles.take(3) else nouvelles
        if (!Push.actif(context) && autorisees(context)) aMontrer.reversed().forEach { montrer(context, it) }   // plus ancienne d'abord

        vues += liste.data.map { it.id }
        prefs.edit()
            .putStringSet(CLE_VUES, vues.toList().takeLast(300).toSet())
            .putString(CLE_DERNIERE, derniere)
            .putBoolean(CLE_INITIALISE, true)
            .apply()
        return liste.meta?.nonLues ?: compteur.nonLues
    }

    /** Notification reçue par push alors que l'application est ouverte : affichée ici, et retenue comme vue. */
    fun montrerPush(context: Context, id: String?, titre: String, texte: String, donnees: Map<String, String>) {
        if (id != null) {
            val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            val vues = prefs.getStringSet(CLE_VUES, emptySet())!!.toMutableSet().apply { add(id) }
            prefs.edit().putStringSet(CLE_VUES, vues).apply()
        }
        if (!autorisees(context)) return
        publier(
            context, id ?: titre, titre, texte, donnees["categorie"],
            donnees["ecran"], donnees["reference"], donnees["id"]?.toLongOrNull(), donnees["lien"],
        )
    }

    /** Déconnexion : on retire les notifications affichées et la mémoire de ce compte. */
    fun oublier(context: Context) {
        NotificationManagerCompat.from(context).cancelAll()
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().clear().apply()
    }

    private fun montrer(context: Context, n: Notification) = publier(
        context, n.id, n.titre, n.texte, n.categorie, n.cible?.ecran, n.cible?.reference, n.cible?.id, n.lien,
    )

    private fun publier(
        context: Context, id: String, titre: String, texte: String, categorie: String?,
        ecran: String?, reference: String?, cibleId: Long?, lien: String?,
    ) {
        val intent = Intent(context, MainActivity::class.java)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP)
            .putExtra(EXTRA_ID, id)
            .putExtra(EXTRA_ECRAN, ecran)
            .putExtra(EXTRA_REFERENCE, reference)
            .putExtra(EXTRA_CIBLE_ID, cibleId?.toString())
            .putExtra(EXTRA_LIEN, lien)
        val ouverture = PendingIntent.getActivity(context, id.hashCode(), intent, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
        val notification = NotificationCompat.Builder(context, CANAL)
            .setSmallIcon(R.drawable.ic_stat_vivi)
            .setColor(Vivi.Teal700.toArgb())
            .setContentTitle(titre.ifBlank { "VIVI" })
            .setContentText(texte)
            .setStyle(NotificationCompat.BigTextStyle().bigText(texte))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(
                when (categorie) {
                    "offre" -> NotificationCompat.CATEGORY_PROMO
                    "reservation" -> NotificationCompat.CATEGORY_EVENT
                    else -> NotificationCompat.CATEGORY_STATUS
                },
            )
            .setAutoCancel(true)
            .setContentIntent(ouverture)
            .build()
        try {
            NotificationManagerCompat.from(context).notify(id.hashCode(), notification)
        } catch (_: SecurityException) {
            // Autorisation retirée entre-temps
        }
    }
}

/** Vérification en arrière-plan, lancée par WorkManager. */
class TravailNotifications(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    override suspend fun doWork(): Result {
        Alertes.verifier(applicationContext)
        return Result.success()
    }
}
