package bj.vivi.app.ui.composants

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.ArrowDropDown
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import bj.vivi.app.ui.theme.Vivi

/** Pays proposés pour l'indicatif WhatsApp : Bénin d'abord, puis voisins, Afrique, Europe, Amérique, autres. */
data class Pays(val nom: String, val drapeau: String, val indicatif: String)

val PaysWhatsapp = listOf(
    Pays("Bénin", "🇧🇯", "229"), Pays("Togo", "🇹🇬", "228"), Pays("Nigeria", "🇳🇬", "234"), Pays("Niger", "🇳🇪", "227"),
    Pays("Burkina Faso", "🇧🇫", "226"), Pays("Côte d’Ivoire", "🇨🇮", "225"), Pays("Ghana", "🇬🇭", "233"), Pays("Sénégal", "🇸🇳", "221"),
    Pays("Mali", "🇲🇱", "223"), Pays("Guinée", "🇬🇳", "224"), Pays("Cameroun", "🇨🇲", "237"), Pays("Gabon", "🇬🇦", "241"),
    Pays("Congo", "🇨🇬", "242"), Pays("RD Congo", "🇨🇩", "243"), Pays("Tchad", "🇹🇩", "235"), Pays("Maroc", "🇲🇦", "212"),
    Pays("Tunisie", "🇹🇳", "216"), Pays("Algérie", "🇩🇿", "213"), Pays("Afrique du Sud", "🇿🇦", "27"),
    Pays("France", "🇫🇷", "33"), Pays("Belgique", "🇧🇪", "32"), Pays("Suisse", "🇨🇭", "41"), Pays("Luxembourg", "🇱🇺", "352"),
    Pays("Allemagne", "🇩🇪", "49"), Pays("Espagne", "🇪🇸", "34"), Pays("Italie", "🇮🇹", "39"), Pays("Pays-Bas", "🇳🇱", "31"),
    Pays("Royaume-Uni", "🇬🇧", "44"), Pays("États-Unis / Canada", "🇺🇸", "1"), Pays("Brésil", "🇧🇷", "55"),
    Pays("Chine", "🇨🇳", "86"), Pays("Inde", "🇮🇳", "91"), Pays("Émirats arabes unis", "🇦🇪", "971"), Pays("Liban", "🇱🇧", "961"),
)

object Whatsapp {
    /** Numéro complet au format international (« +2290197000000 », « +33612345678 »), attendu par la plateforme. */
    fun international(indicatif: String, numero: String): String {
        val chiffres = numero.filter(Char::isDigit)
        // Au Bénin le 0 initial fait partie du numéro (01…) ; ailleurs, le 0 national tombe après l'indicatif
        return "+" + indicatif + if (indicatif == "229") chiffres else chiffres.trimStart('0')
    }

    fun valide(indicatif: String, numero: String): Boolean {
        val chiffres = numero.filter(Char::isDigit)
        if (indicatif == "229") return chiffres.length == 10 && chiffres.startsWith("01")
        val total = international(indicatif, numero).length - 1
        return chiffres.trimStart('0').length >= 6 && total in 8..15
    }

    /** « +33 6 12 34 56 78 » → (« 33 », « 612345678 ») ; numéro béninois local « 0197000000 » → (« 229 », …). */
    fun decouper(valeur: String?): Pair<String, String>? {
        val v = valeur?.trim().orEmpty()
        if (v.isEmpty()) return null
        val chiffres = v.filter(Char::isDigit).let { if (v.startsWith("00")) it.drop(2) else it }
        if (!v.startsWith("+") && !v.startsWith("00")) return "229" to chiffres.takeLast(10)
        val pays = PaysWhatsapp.sortedByDescending { it.indicatif.length }.firstOrNull { chiffres.startsWith(it.indicatif) }
            ?: return null
        return pays.indicatif to chiffres.removePrefix(pays.indicatif)
    }
}

/**
 * Numéro WhatsApp de n'importe quel pays : indicatif choisi dans une liste (drapeau), numéro national à côté.
 * Libellé visible au-dessus, aide ou erreur juste en dessous (comme ChampTexte).
 */
@Composable
fun ChampWhatsapp(
    indicatif: String,
    onIndicatif: (String) -> Unit,
    numero: String,
    onNumero: (String) -> Unit,
    modifier: Modifier = Modifier,
    libelle: String = "Numéro WhatsApp *",
    aide: String? = null,
    erreur: String? = null,
) {
    var liste by remember { mutableStateOf(false) }
    val pays = PaysWhatsapp.firstOrNull { it.indicatif == indicatif } ?: PaysWhatsapp.first()
    Column(modifier) {
        Text(libelle, style = MaterialTheme.typography.labelMedium, color = Vivi.Ink, modifier = Modifier.padding(bottom = 6.dp))
        OutlinedTextField(
            value = numero,
            onValueChange = { v -> onNumero(v.filter { it.isDigit() || it == ' ' }.take(18)) },
            modifier = Modifier.fillMaxWidth(),
            placeholder = { Text(if (indicatif == "229") "01 97 00 00 00" else "Numéro sans l’indicatif", color = Vivi.Muted) },
            leadingIcon = {
                Row(
                    Modifier.heightIn(min = 48.dp).padding(start = 8.dp)
                        .clickable(role = Role.Button, onClickLabel = "Choisir le pays") { liste = true }
                        .semantics { contentDescription = "Pays : ${pays.nom}, indicatif +${pays.indicatif}" }
                        .padding(horizontal = 6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Text(pays.drapeau, style = MaterialTheme.typography.titleMedium)
                    Spacer(Modifier.width(6.dp))
                    Text("+${pays.indicatif}", color = Vivi.Ink, fontWeight = FontWeight.SemiBold)
                    Icon(Icons.Outlined.ArrowDropDown, null, tint = Vivi.Muted)
                }
                DropdownMenu(expanded = liste, onDismissRequest = { liste = false }, containerColor = Vivi.White) {
                    PaysWhatsapp.forEach { p ->
                        DropdownMenuItem(
                            text = { Text("${p.drapeau}  ${p.nom}  (+${p.indicatif})", fontWeight = if (p == pays) FontWeight.Bold else FontWeight.Normal) },
                            onClick = { onIndicatif(p.indicatif); liste = false },
                        )
                    }
                }
            },
            isError = erreur != null,
            singleLine = true,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
            shape = RoundedCornerShape(12.dp),
            textStyle = MaterialTheme.typography.bodyLarge.copy(color = Vivi.Ink),
            colors = OutlinedTextFieldDefaults.colors(
                focusedBorderColor = Vivi.Teal700, unfocusedBorderColor = Vivi.LineStrong,
                errorBorderColor = Vivi.Danger, cursorColor = Vivi.Teal700,
                focusedContainerColor = Vivi.White, unfocusedContainerColor = Vivi.White,
            ),
        )
        when {
            erreur != null -> Text(erreur, color = Vivi.Danger, style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Danger), modifier = Modifier.padding(top = 6.dp))
            aide != null -> Text(aide, style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(top = 6.dp))
        }
    }
}
