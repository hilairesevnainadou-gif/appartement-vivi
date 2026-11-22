package bj.vivi.app.ui.composants

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.SnackbarData
import androidx.compose.material3.SnackbarDuration
import androidx.compose.material3.SnackbarVisuals
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import bj.vivi.app.ui.Message
import bj.vivi.app.ui.TypeMessage
import bj.vivi.app.ui.theme.Vivi

/** Contenu d'un message pour le SnackbarHost : texte + type (couleur, icône, durée). */
class VisuelsMessage(val contenu: Message) : SnackbarVisuals {
    override val message: String get() = contenu.texte
    override val actionLabel: String? = null
    override val withDismissAction: Boolean = true
    override val duration: SnackbarDuration =
        if (contenu.type == TypeMessage.Erreur || contenu.texte.length > 70) SnackbarDuration.Long else SnackbarDuration.Short
}

private data class Style(val fond: Color, val pastille: Color, val icone: ImageVector, val annonce: String)

private fun style(type: TypeMessage) = when (type) {
    // Fonds pleins et foncés + texte blanc : contraste AA (au moins 4,5:1), lisible en plein soleil
    TypeMessage.Succes -> Style(Vivi.Success, Color.White.copy(alpha = .18f), Icons.Outlined.CheckCircle, "Succès")
    TypeMessage.Erreur -> Style(Vivi.Danger, Color.White.copy(alpha = .18f), Icons.Outlined.ErrorOutline, "Erreur")
    TypeMessage.Info -> Style(Vivi.Teal900, Color.White.copy(alpha = .14f), Icons.Outlined.Info, "Information")
}

/** Bandeau de message : pastille d'icône, texte blanc en gras léger, bouton fermer. */
@Composable
fun BulleMessage(donnees: SnackbarData) {
    val contenu = (donnees.visuals as? VisuelsMessage)?.contenu ?: Message(donnees.visuals.message, TypeMessage.Info)
    val s = style(contenu.type)
    val forme = RoundedCornerShape(16.dp)
    Row(
        Modifier.padding(horizontal = 16.dp, vertical = 8.dp).fillMaxWidth()
            .shadow(10.dp, forme, ambientColor = Vivi.Ink, spotColor = Vivi.Ink)
            .clip(forme).background(s.fond)
            .heightIn(min = 56.dp)
            .padding(start = 12.dp, end = 4.dp, top = 10.dp, bottom = 10.dp)
            .semantics { liveRegion = if (contenu.type == TypeMessage.Erreur) LiveRegionMode.Assertive else LiveRegionMode.Polite },
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(34.dp).clip(CircleShape).background(s.pastille), contentAlignment = Alignment.Center) {
            Icon(s.icone, contentDescription = s.annonce, tint = Color.White, modifier = Modifier.size(20.dp))
        }
        Spacer(Modifier.width(12.dp))
        Text(
            contenu.texte, color = Color.White, fontWeight = FontWeight.Medium,
            style = MaterialTheme.typography.bodyMedium.copy(color = Color.White), modifier = Modifier.weight(1f),
        )
        IconButton(onClick = { donnees.dismiss() }) {
            Icon(Icons.Outlined.Close, contentDescription = "Fermer le message", tint = Color.White.copy(alpha = .85f), modifier = Modifier.size(20.dp))
        }
    }
}
