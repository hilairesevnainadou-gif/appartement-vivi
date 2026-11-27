package bj.vivi.app.ui.composants

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextField
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/** Mots tapés dans la barre de l'accueil, repris par l'écran Logements (puis effacés). */
object RechercheDemandee {
    private val _mots = MutableStateFlow<String?>(null)
    val mots: StateFlow<String?> = _mots

    fun envoyer(texte: String) { _mots.value = texte.trim() }
    fun prendre(): String? = _mots.value.also { _mots.value = null }
}

/** Champ « Rechercher » : titre, quartier, ville ou adresse (paramètre q de GET /logements). */
@Composable
fun BarreRecherche(
    texte: String,
    onTexte: (String) -> Unit,
    modifier: Modifier = Modifier,
    indication: String = "Quartier, ville, nom du logement…",
    onValider: (() -> Unit)? = null,
) {
    val focus = LocalFocusManager.current
    TextField(
        value = texte,
        onValueChange = { onTexte(it.take(60)) },
        modifier = modifier.fillMaxWidth().heightIn(min = 52.dp),
        singleLine = true,
        placeholder = { Text(indication, color = Vivi.Muted) },
        leadingIcon = { Icon(Icons.Outlined.Search, null, tint = Vivi.Teal700) },
        trailingIcon = if (texte.isNotEmpty()) {
            { IconButton(onClick = { onTexte("") }) { Icon(Icons.Outlined.Close, "Effacer", tint = Vivi.Muted) } }
        } else null,
        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
        keyboardActions = KeyboardActions(onSearch = { focus.clearFocus(); onValider?.invoke() }),
        shape = RoundedCornerShape(16.dp),
        colors = TextFieldDefaults.colors(
            focusedContainerColor = Vivi.White, unfocusedContainerColor = Vivi.White,
            focusedIndicatorColor = androidx.compose.ui.graphics.Color.Transparent,
            unfocusedIndicatorColor = androidx.compose.ui.graphics.Color.Transparent,
            cursorColor = Vivi.Teal700,
        ),
    )
}
