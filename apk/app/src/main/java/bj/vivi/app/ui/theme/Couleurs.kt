package bj.vivi.app.ui.theme

import androidx.compose.ui.graphics.Color

/**
 * Jetons de couleur de la charte VIVI, repris tels quels de maquette/assets/css/vivi.css (:root).
 * Les écrans n'utilisent jamais de couleur en dur : seulement ces jetons ou MaterialTheme.colorScheme.
 */
object Vivi {
    val Teal50 = Color(0xFFEFF8F7)
    val Teal100 = Color(0xFFD8EEEC)
    val Teal200 = Color(0xFFB4DEDB)
    val Teal400 = Color(0xFF64B8B8)   // turquoise du logo : décor uniquement
    val Teal600 = Color(0xFF1F8583)
    val Teal700 = Color(0xFF136F6E)   // turquoise lisible : liens, boutons secondaires
    val Teal800 = Color(0xFF0E5655)
    val Teal900 = Color(0xFF0A3D3E)   // textes de marque, fonds sombres

    val Orange50 = Color(0xFFFFF5E6)
    val Orange100 = Color(0xFFFDE7C4)
    val Orange400 = Color(0xFFF4AD4C) // orange du logo : action principale
    val Orange500 = Color(0xFFEE9B2B)
    val Orange600 = Color(0xFFD7841A)
    val OrangeInk = Color(0xFF7A4500) // texte sur fond orange clair

    val Ink = Color(0xFF14282A)       // titres, texte sur bouton orange
    val Body = Color(0xFF3B4D4F)
    val Muted = Color(0xFF5F7072)
    val Line = Color(0xFFE2E8E7)
    val LineStrong = Color(0xFFC9D5D4)
    val Sand = Color(0xFFFAF7F2)
    val Segmented = Color(0xFFEDF2F1)

    val Success = Color(0xFF1E7D4F)
    val SuccessBg = Color(0xFFE7F5EE)
    val SuccessInk = Color(0xFF14633D)
    val Danger = Color(0xFFB83A2B)
    val DangerBg = Color(0xFFFBECEA)

    val Chart = Color(0xFF0D9488)     // série des graphiques (vivi-pro.css)
    val White = Color(0xFFFFFFFF)
}
