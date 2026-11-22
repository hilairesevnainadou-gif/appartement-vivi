package bj.vivi.app.ui.theme

import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontVariation
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import bj.vivi.app.R

/* ---------- Polices : Outfit (titres) et DM Sans (texte), comme le site ---------- */

@OptIn(androidx.compose.ui.text.ExperimentalTextApi::class)
private fun variable(res: Int, poids: Int) = Font(
    res, FontWeight(poids),
    variationSettings = FontVariation.Settings(FontVariation.weight(poids)),
)

val Outfit = FontFamily(
    variable(R.font.outfit, 400), variable(R.font.outfit, 500), variable(R.font.outfit, 600),
    variable(R.font.outfit, 700), variable(R.font.outfit, 800),
)
val DmSans = FontFamily(
    variable(R.font.dm_sans, 400), variable(R.font.dm_sans, 500), variable(R.font.dm_sans, 600), variable(R.font.dm_sans, 700),
)

private val titre = TextStyle(fontFamily = Outfit, color = Vivi.Ink, letterSpacing = (-0.01).sp)
private val texte = TextStyle(fontFamily = DmSans, color = Vivi.Body)

/** Échelle typographique : corps à 16 sp, interligne 1,5 (vivi.css : line-height 1.6). */
val ViviTypographie = Typography(
    displaySmall = titre.copy(fontSize = 32.sp, lineHeight = 36.sp, fontWeight = FontWeight.Bold),
    headlineLarge = titre.copy(fontSize = 28.sp, lineHeight = 32.sp, fontWeight = FontWeight.Bold),
    headlineMedium = titre.copy(fontSize = 24.sp, lineHeight = 28.sp, fontWeight = FontWeight.SemiBold),
    headlineSmall = titre.copy(fontSize = 20.sp, lineHeight = 26.sp, fontWeight = FontWeight.SemiBold),
    titleLarge = titre.copy(fontSize = 18.sp, lineHeight = 24.sp, fontWeight = FontWeight.SemiBold),
    titleMedium = titre.copy(fontSize = 16.sp, lineHeight = 22.sp, fontWeight = FontWeight.SemiBold),
    titleSmall = titre.copy(fontSize = 14.sp, lineHeight = 20.sp, fontWeight = FontWeight.SemiBold),
    bodyLarge = texte.copy(fontSize = 16.sp, lineHeight = 24.sp),
    bodyMedium = texte.copy(fontSize = 15.sp, lineHeight = 22.sp),
    bodySmall = texte.copy(fontSize = 13.sp, lineHeight = 18.sp, color = Vivi.Muted),
    labelLarge = texte.copy(fontSize = 16.sp, lineHeight = 20.sp, fontWeight = FontWeight.SemiBold),
    labelMedium = texte.copy(fontSize = 13.sp, lineHeight = 16.sp, fontWeight = FontWeight.SemiBold),
    labelSmall = texte.copy(fontSize = 12.sp, lineHeight = 16.sp, fontWeight = FontWeight.SemiBold),
)

/** Rayons de la charte : 10, 12, 16 et 24 px. */
val ViviFormes = Shapes(
    extraSmall = RoundedCornerShape(8.dp),
    small = RoundedCornerShape(12.dp),
    medium = RoundedCornerShape(16.dp),
    large = RoundedCornerShape(20.dp),
    extraLarge = RoundedCornerShape(24.dp),
)

private val Palette = lightColorScheme(
    primary = Vivi.Teal700,
    onPrimary = Vivi.White,
    primaryContainer = Vivi.Teal50,
    onPrimaryContainer = Vivi.Teal800,
    secondary = Vivi.Orange400,
    onSecondary = Vivi.Ink,
    secondaryContainer = Vivi.Orange50,
    onSecondaryContainer = Vivi.OrangeInk,
    tertiary = Vivi.Teal900,
    onTertiary = Vivi.White,
    background = Vivi.Sand,
    onBackground = Vivi.Ink,
    surface = Vivi.White,
    onSurface = Vivi.Ink,
    surfaceVariant = Vivi.Segmented,
    onSurfaceVariant = Vivi.Muted,
    surfaceContainerLowest = Vivi.White,
    surfaceContainerLow = Vivi.White,
    surfaceContainer = Vivi.White,
    surfaceContainerHigh = Vivi.White,
    surfaceContainerHighest = Vivi.Segmented,
    outline = Vivi.LineStrong,
    outlineVariant = Vivi.Line,
    error = Vivi.Danger,
    onError = Vivi.White,
    errorContainer = Vivi.DangerBg,
    onErrorContainer = Vivi.Danger,
)

/**
 * Thème clair uniquement, comme le site : la charte n'a pas encore de variante sombre validée.
 * (Ajouter un darkColorScheme ici le jour où elle existe.)
 */
@Composable
fun ViviTheme(content: @Composable () -> Unit) {
    MaterialTheme(colorScheme = Palette, typography = ViviTypographie, shapes = ViviFormes, content = content)
}
