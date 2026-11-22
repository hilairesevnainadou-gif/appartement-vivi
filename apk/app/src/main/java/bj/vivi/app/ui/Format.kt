package bj.vivi.app.ui

import java.text.NumberFormat
import java.time.LocalDate
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale

/** Formats d'affichage communs, en français du Bénin (fuseau Africa/Porto-Novo, montants en FCFA). */
object Fmt {
    val locale: Locale = Locale.FRANCE
    val fuseau: ZoneId = ZoneId.of("Africa/Porto-Novo")

    private val nombre = NumberFormat.getIntegerInstance(locale)

    /** 12500 → « 12 500 FCFA » (espace insécable, comme Montant::fcfa côté serveur). */
    fun fcfa(montant: Int?): String = nombre.format(montant ?: 0).replace(' ', ' ') + " FCFA"

    /** 12.0 → « 12 », 7.5 → « 7,5 » (taux de commission). */
    fun taux(t: Double): String = "%.2f".format(locale, t).trimEnd('0').trimEnd(',')

    fun nombre(n: Int): String = nombre.format(n).replace(' ', ' ')

    /** « 0197000000 » → « 01 97 00 00 00 » (comme Telephone::lisible côté serveur). */
    fun telephone(numero: String): String = numero.chunked(2).joinToString(" ")

    fun dateHeure(iso: String?): OffsetDateTime? = iso?.let { runCatching { OffsetDateTime.parse(it).atZoneSameInstant(fuseau).toOffsetDateTime() }.getOrNull() }

    fun jour(iso: String?): LocalDate? = iso?.let { runCatching { LocalDate.parse(it.take(10)) }.getOrNull() }

    private val fHeure = DateTimeFormatter.ofPattern("HH:mm", locale)
    private val fCourt = DateTimeFormatter.ofPattern("EEE d MMM", locale)
    private val fLong = DateTimeFormatter.ofPattern("EEEE d MMMM", locale)
    private val fComplet = DateTimeFormatter.ofPattern("EEEE d MMMM yyyy", locale)
    private val fNumerique = DateTimeFormatter.ofPattern("dd/MM/yyyy", locale)
    private val fMois = DateTimeFormatter.ofPattern("MMMM yyyy", locale)

    fun heure(iso: String?): String = dateHeure(iso)?.format(fHeure) ?: ""

    /** « Jeu. 13 nov. » */
    fun jourCourt(iso: String?): String = (dateHeure(iso)?.toLocalDate() ?: jour(iso))?.format(fCourt)?.capitaliser() ?: ""

    /** « Jeudi 13 novembre » */
    fun jourLong(iso: String?): String = (dateHeure(iso)?.toLocalDate() ?: jour(iso))?.format(fLong)?.capitaliser() ?: ""

    fun jourLong(d: LocalDate): String = d.format(fLong).capitaliser()
    fun jourCourt(d: LocalDate): String = d.format(fCourt).capitaliser()
    fun jourComplet(iso: String?): String = (dateHeure(iso)?.toLocalDate() ?: jour(iso))?.format(fComplet)?.capitaliser() ?: ""
    fun numerique(iso: String?): String = (dateHeure(iso)?.toLocalDate() ?: jour(iso))?.format(fNumerique) ?: ""
    fun mois(d: LocalDate): String = d.format(fMois).capitaliser()

    /** « Aujourd’hui à 14:00 », « Demain à 09:00 » ou « Jeu. 13 nov. à 14:00 ». */
    fun quand(iso: String?): String {
        val d = dateHeure(iso) ?: return ""
        val aujourdhui = LocalDate.now(fuseau)
        val jour = when (d.toLocalDate()) {
            aujourdhui -> "Aujourd’hui"
            aujourdhui.plusDays(1) -> "Demain"
            else -> d.format(fCourt).capitaliser()
        }
        return "$jour à ${d.format(fHeure)}"
    }

    /** « 2026-11-12T11:11:38+01:00 » → « novembre 2026 » (jamais la date brute de l'API). */
    fun membreDepuis(iso: String?): String? =
        (dateHeure(iso)?.toLocalDate() ?: jour(iso))?.format(fMois)
            ?: iso?.takeIf { it.isNotBlank() && !it.first().isDigit() }   // déjà un libellé lisible

    fun aujourdhui(): LocalDate = LocalDate.now(fuseau)

    /** Salutation selon l'heure locale : Bonjour (5 h – 12 h), Bon après-midi (12 h – 18 h), Bonsoir (18 h – 5 h). */
    fun salutation(heure: Int = java.time.LocalTime.now(fuseau).hour): String = when (heure) {
        in 5..11 -> "Bonjour"
        in 12..17 -> "Bon après-midi"
        else -> "Bonsoir"
    }

    private fun String.capitaliser() = replaceFirstChar { it.titlecase(locale) }
}

fun pluriel(n: Int, mot: String) = "$n $mot" + if (n > 1) "s" else ""
