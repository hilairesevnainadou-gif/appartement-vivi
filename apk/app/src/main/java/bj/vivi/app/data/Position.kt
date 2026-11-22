package bj.vivi.app.data

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Build
import android.os.CancellationSignal
import android.os.Looper
import androidx.core.content.ContextCompat
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull
import kotlin.coroutines.resume

/**
 * Position approximative du téléphone (« Près de moi ») : réseau d'abord, GPS ensuite.
 * Sans Google Play Services ; gardée en mémoire pour la durée du lancement.
 */
object Position {
    data class Point(val lat: Double, val lng: Double)

    @Volatile var derniere: Point? = null
        private set

    fun autorisee(context: Context) =
        ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED

    /** Localisation activée dans les réglages du téléphone. */
    fun active(context: Context): Boolean =
        context.getSystemService(LocationManager::class.java)?.let { androidx.core.location.LocationManagerCompat.isLocationEnabled(it) } ?: false

    @SuppressLint("MissingPermission")
    suspend fun obtenir(context: Context): Point? {
        derniere?.let { return it }
        if (!autorisee(context)) return null
        val lm = context.getSystemService(LocationManager::class.java) ?: return null
        val fournisseurs = lm.getProviders(true)

        val connue = fournisseurs.mapNotNull { runCatching { lm.getLastKnownLocation(it) }.getOrNull() }.maxByOrNull { it.time }
        val recente = connue?.takeIf { System.currentTimeMillis() - it.time < 30 * 60_000 }
        val position = recente ?: run {
            val fournisseur = listOf(LocationManager.NETWORK_PROVIDER, LocationManager.GPS_PROVIDER).firstOrNull { it in fournisseurs }
            fournisseur?.let { withTimeoutOrNull(6_000) { demander(context, lm, it) } } ?: connue
        }
        return position?.let { Point(it.latitude, it.longitude) }?.also { derniere = it }
    }

    @SuppressLint("MissingPermission")
    private suspend fun demander(context: Context, lm: LocationManager, fournisseur: String): Location? =
        suspendCancellableCoroutine { suite ->
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                val annulation = CancellationSignal()
                suite.invokeOnCancellation { annulation.cancel() }
                lm.getCurrentLocation(fournisseur, annulation, context.mainExecutor) { if (suite.isActive) suite.resume(it) }
            } else {
                val ecoute = object : LocationListener {
                    override fun onLocationChanged(l: Location) { if (suite.isActive) suite.resume(l) }
                    @Deprecated("API < 29") override fun onStatusChanged(p: String?, s: Int, e: android.os.Bundle?) {}
                    override fun onProviderEnabled(p: String) {}
                    override fun onProviderDisabled(p: String) { if (suite.isActive) suite.resume(null) }
                }
                suite.invokeOnCancellation { lm.removeUpdates(ecoute) }
                @Suppress("DEPRECATION")
                lm.requestSingleUpdate(fournisseur, ecoute, Looper.getMainLooper())
            }
        }
}
