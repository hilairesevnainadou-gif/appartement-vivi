package bj.vivi.app.data

import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock

/** Configuration publique (GET /configuration) chargée une fois par lancement : villes, formules, filtres, contact. */
object Catalogue {
    private val verrou = Mutex()
    @Volatile var configuration: Configuration? = null
        private set

    suspend fun obtenir(): Configuration? = verrou.withLock {
        configuration ?: appel { configuration() }.getOrNull()?.data?.also { configuration = it }
    }

    /** « 1.2.0 » < « 1.10.0 » : comparaison par numéros. */
    fun miseAJourRequise(versionInstallee: String): Boolean {
        val min = configuration?.versionMinimale ?: return false
        val a = versionInstallee.split('.').map { it.toIntOrNull() ?: 0 }
        val b = min.split('.').map { it.toIntOrNull() ?: 0 }
        for (i in 0 until maxOf(a.size, b.size)) {
            val x = a.getOrElse(i) { 0 }
            val y = b.getOrElse(i) { 0 }
            if (x != y) return x < y
        }
        return false
    }
}
