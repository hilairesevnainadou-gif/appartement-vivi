package bj.vivi.app.ui.composants

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Person
import androidx.compose.foundation.layout.offset
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Add
import androidx.compose.material.icons.outlined.CloudOff
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.Remove
import androidx.compose.material.icons.rounded.Star
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CenterAlignedTopAppBar
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LocalContentColor
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.material3.TopAppBarScrollBehavior
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import bj.vivi.app.R
import bj.vivi.app.data.Enumeration
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.theme.Outfit
import bj.vivi.app.ui.theme.Vivi
import coil.compose.AsyncImage

/* ====================================================================================
 * Composants de la charte VIVI (équivalents des classes de maquette/assets/css/vivi.css).
 * Cibles tactiles ≥ 48 dp, contraste AA, libellés d'accessibilité sur les icônes seules.
 * ==================================================================================== */

/* ---------- Boutons (.btn-primary orange, .btn-secondary turquoise, .btn-outline) ---------- */

private val FormeBouton = RoundedCornerShape(12.dp)
private val HauteurBouton = 52.dp

/** Action principale : orange du logo, texte encre (contraste 9,6:1). Une seule par écran. */
@Composable
fun BoutonPrincipal(
    texte: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    actif: Boolean = true,
    enCours: Boolean = false,
    icone: ImageVector? = null,
) {
    Button(
        onClick = onClick,
        enabled = actif && !enCours,
        modifier = modifier.heightIn(min = HauteurBouton),
        shape = FormeBouton,
        colors = ButtonDefaults.buttonColors(
            containerColor = Vivi.Orange400, contentColor = Vivi.Ink,
            // En cours : couleur pleine + roue (ce n'est pas un bouton désactivé)
            disabledContainerColor = if (enCours) Vivi.Orange400 else Vivi.Orange400.copy(alpha = .45f),
            disabledContentColor = if (enCours) Vivi.Ink else Vivi.Ink.copy(alpha = .6f),
        ),
        contentPadding = PaddingValues(horizontal = 20.dp, vertical = 12.dp),
    ) { ContenuBouton(texte, enCours, icone, Vivi.Ink) }
}

/** Action secondaire pleine : turquoise lisible. */
@Composable
fun BoutonSecondaire(
    texte: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    actif: Boolean = true,
    enCours: Boolean = false,
    icone: ImageVector? = null,
) {
    Button(
        onClick = onClick,
        enabled = actif && !enCours,
        modifier = modifier.heightIn(min = HauteurBouton),
        shape = FormeBouton,
        colors = ButtonDefaults.buttonColors(containerColor = Vivi.Teal700, contentColor = Vivi.White,
            disabledContainerColor = if (enCours) Vivi.Teal700 else Vivi.Teal700.copy(alpha = .4f), disabledContentColor = Vivi.White),
        contentPadding = PaddingValues(horizontal = 20.dp, vertical = 12.dp),
    ) { ContenuBouton(texte, enCours, icone, Vivi.White) }
}

/** Bouton contour (.btn-outline-primary) ; [danger] pour annuler une réservation. */
@Composable
fun BoutonContour(
    texte: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    actif: Boolean = true,
    enCours: Boolean = false,
    icone: ImageVector? = null,
    danger: Boolean = false,
) {
    val couleur = if (danger) Vivi.Danger else Vivi.Teal700
    OutlinedButton(
        onClick = onClick,
        enabled = actif && !enCours,
        modifier = modifier.heightIn(min = HauteurBouton),
        shape = FormeBouton,
        border = BorderStroke(1.5.dp, if (actif) couleur else Vivi.LineStrong),
        colors = ButtonDefaults.outlinedButtonColors(contentColor = couleur),
        contentPadding = PaddingValues(horizontal = 18.dp, vertical = 12.dp),
    ) { ContenuBouton(texte, enCours, icone, couleur) }
}

@Composable
fun BoutonTexte(texte: String, onClick: () -> Unit, modifier: Modifier = Modifier, icone: ImageVector? = null, couleur: Color = Vivi.Teal700) {
    TextButton(onClick = onClick, modifier = modifier.heightIn(min = 48.dp), colors = ButtonDefaults.textButtonColors(contentColor = couleur)) {
        if (icone != null) {
            Icon(icone, contentDescription = null, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(6.dp))
        }
        Text(texte, style = MaterialTheme.typography.labelLarge, color = couleur)
    }
}

@Composable
private fun RowScope.ContenuBouton(texte: String, enCours: Boolean, icone: ImageVector?, couleur: Color) {
    if (enCours) {
        CircularProgressIndicator(Modifier.size(20.dp), color = couleur, strokeWidth = 2.dp)
        Spacer(Modifier.width(10.dp))
    } else if (icone != null) {
        Icon(icone, contentDescription = null, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(8.dp))
    }
    // Couleur explicite : labelLarge du thème porte sa propre couleur, qui écraserait celle du bouton
    Text(texte, style = MaterialTheme.typography.labelLarge, color = LocalContentColor.current, maxLines = 1, overflow = TextOverflow.Ellipsis)
}

/* ---------- Puces (.chip) ---------- */

enum class TonPuce(val fond: Color, val texte: Color) {
    Teal(Vivi.Teal50, Vivi.Teal800),
    Orange(Vivi.Orange50, Vivi.OrangeInk),
    Ligne(Vivi.White, Vivi.Body),
    Succes(Vivi.SuccessBg, Vivi.SuccessInk),
    Danger(Vivi.DangerBg, Vivi.Danger),
    Neutre(Vivi.Segmented, Vivi.Body),
    Sombre(Vivi.Teal900.copy(alpha = .86f), Vivi.White),
}

@Composable
fun Puce(texte: String, ton: TonPuce = TonPuce.Teal, icone: ImageVector? = null, modifier: Modifier = Modifier) {
    Row(
        modifier
            .clip(CircleShape)
            .background(ton.fond)
            .then(if (ton == TonPuce.Ligne) Modifier.border(1.dp, Vivi.LineStrong, CircleShape) else Modifier)
            .padding(horizontal = 10.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (icone != null) {
            Icon(icone, null, tint = ton.texte, modifier = Modifier.size(14.dp))
            Spacer(Modifier.width(4.dp))
        }
        Text(texte, color = ton.texte, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, maxLines = 1)
    }
}

/** Statut d'une réservation, d'un logement, d'un retrait ou d'un paiement, avec la couleur du site. */
@Composable
fun PuceStatut(statut: Enumeration, modifier: Modifier = Modifier) {
    val ton = when (statut.valeur) {
        "confirmee", "publie", "actif", "en_validation" -> TonPuce.Teal
        "en_cours", "reussi", "valide", "paye", "terminee" -> if (statut.valeur == "terminee") TonPuce.Neutre else TonPuce.Succes
        "en_attente_paiement", "en_attente", "brouillon", "demande" -> TonPuce.Orange
        "consommee", "suspendu" -> TonPuce.Orange
        "annulee", "refuse", "echoue", "rembourse" -> TonPuce.Danger
        else -> TonPuce.Neutre
    }
    Puce(statut.libelle, ton, modifier = modifier)
}

/* ---------- Surfaces ---------- */

/** Carte blanche bordée (.listing-card, .kpi) : rayon 16, bordure #e2e8e7. */
@Composable
fun Carte(
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null,
    remplissage: PaddingValues = PaddingValues(16.dp),
    fond: Color = Vivi.White,
    contenu: @Composable () -> Unit,
) {
    val forme = MaterialTheme.shapes.medium
    Surface(
        modifier = modifier
            .clip(forme)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier),
        shape = forme,
        color = fond,
        border = BorderStroke(1.dp, Vivi.Line),
    ) {
        Box(Modifier.padding(remplissage)) { contenu() }
    }
}

/** Titre de section avec « eyebrow » optionnel (.eyebrow) et action à droite. */
@Composable
fun TitreSection(titre: String, modifier: Modifier = Modifier, surtitre: String? = null, action: (@Composable () -> Unit)? = null) {
    Row(modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            if (surtitre != null) {
                Text(surtitre.uppercase(Fmt.locale), color = Vivi.Teal700, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 1.sp)
                Spacer(Modifier.height(2.dp))
            }
            Text(titre, style = MaterialTheme.typography.titleLarge, modifier = Modifier.semantics { heading() })
        }
        action?.invoke()
    }
}

/** Ligne « libellé … montant » des récapitulatifs de prix. */
@Composable
fun LigneMontant(libelle: String, valeur: String, modifier: Modifier = Modifier, fort: Boolean = false, couleur: Color = Vivi.Ink, aide: String? = null) {
    Row(modifier.fillMaxWidth().padding(vertical = 4.dp), verticalAlignment = Alignment.Top) {
        Column(Modifier.weight(1f)) {
            Text(libelle, style = if (fort) MaterialTheme.typography.titleMedium else MaterialTheme.typography.bodyMedium,
                color = if (fort) Vivi.Ink else Vivi.Body)
            if (aide != null) Text(aide, style = MaterialTheme.typography.bodySmall)
        }
        Spacer(Modifier.width(12.dp))
        Text(valeur, style = if (fort) MaterialTheme.typography.titleLarge else MaterialTheme.typography.bodyMedium,
            fontWeight = if (fort) FontWeight.Bold else FontWeight.SemiBold, color = couleur)
    }
}

@Composable
fun Separateur(modifier: Modifier = Modifier) = HorizontalDivider(modifier, thickness = 1.dp, color = Vivi.Line)

/** Note sur 5 (.rating) : étoile orange, nombre d'avis en gris. */
@Composable
fun Note(note: Double?, nombre: Int, modifier: Modifier = Modifier) {
    if (note == null) {
        Puce("Nouveau", TonPuce.Orange, modifier = modifier)
        return
    }
    Row(
        modifier.clearAndSetSemantics { contentDescription = "Note ${"%.1f".format(Fmt.locale, note)} sur 5, $nombre avis" },
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Rounded.Star, null, tint = Vivi.Orange500, modifier = Modifier.size(16.dp))
        Spacer(Modifier.width(3.dp))
        Text("%.1f".format(Fmt.locale, note), fontWeight = FontWeight.Bold, color = Vivi.Ink, fontSize = 14.sp)
        Text(" ($nombre)", color = Vivi.Muted, fontSize = 13.sp)
    }
}

/** Prix « à partir de » (.price) */
@Composable
fun Prix(montant: String, unite: String, modifier: Modifier = Modifier, prefixe: String? = "À partir de") {
    Column(modifier) {
        if (prefixe != null) Text(prefixe, fontSize = 12.sp, color = Vivi.Muted)
        Row(verticalAlignment = Alignment.Bottom) {
            Text(montant, fontFamily = Outfit, fontWeight = FontWeight.Bold, fontSize = 19.sp, color = Vivi.Ink)
            Spacer(Modifier.width(4.dp))
            Text(unite, fontSize = 14.sp, color = Vivi.Muted, modifier = Modifier.padding(bottom = 2.dp))
        }
    }
}

@Composable
fun Avatar(initiales: String, modifier: Modifier = Modifier, taille: Dp = 44.dp, fond: Color = Vivi.Teal50, texte: Color = Vivi.Teal800) {
    Box(modifier.size(taille).clip(CircleShape).background(fond), contentAlignment = Alignment.Center) {
        Text(initiales.take(2).uppercase(), color = texte, fontWeight = FontWeight.Bold, fontFamily = Outfit, fontSize = (taille.value * .36f).sp)
    }
}

/** Salutation du moment (Bonjour / Bon après-midi / Bonsoir), relue chaque minute tant que l'écran est affiché. */
@Composable
fun salutationDuMoment(): String {
    val texte = androidx.compose.runtime.produceState(bj.vivi.app.ui.Fmt.salutation()) {
        while (true) {
            kotlinx.coroutines.delay(60_000)
            value = bj.vivi.app.ui.Fmt.salutation()
        }
    }
    return texte.value
}

/**
 * Profil en avatar : silhouette (tête et épaules coupées par le cercle), orange si connecté,
 * blanc translucide pour un visiteur. Dans les en-têtes (onClick) : rond de 36 dp dans une zone
 * tactile de 48 dp, ouvre le compte. Sur « Mon compte » (onClick = null) : simple image.
 */
@Composable
fun AvatarProfil(connecte: Boolean, nom: String?, onClick: (() -> Unit)?, modifier: Modifier = Modifier, taille: Dp = 36.dp, contour: Color = Vivi.White.copy(alpha = .85f)) {
    val zone = if (onClick != null) {
        modifier.size(maxOf(taille, 48.dp)).clip(CircleShape)
            .clickable(onClickLabel = "Ouvrir mon compte", role = Role.Button, onClick = onClick)
            .clearAndSetSemantics { contentDescription = if (nom.isNullOrBlank()) "Mon compte, se connecter" else "Mon compte, $nom" }
    } else {
        modifier.clearAndSetSemantics { contentDescription = "Photo de profil" + (nom?.let { ", $it" } ?: "") }
    }
    Box(zone, contentAlignment = Alignment.Center) {
        Box(
            Modifier.size(taille).clip(CircleShape)
                .background(if (connecte) Vivi.Orange400 else Vivi.White.copy(alpha = .16f))
                .border(taille * .05f, contour, CircleShape),
            contentAlignment = Alignment.BottomCenter,
        ) {
            Icon(
                Icons.Filled.Person, null, tint = if (connecte) Vivi.Teal900 else Vivi.White,
                modifier = Modifier.size(taille * .84f).offset(y = taille * .1f),
            )
        }
    }
}

/** Image distante avec fond turquoise clair pendant le chargement (pas de saut de mise en page). */
@Composable
fun PhotoLogement(url: String?, description: String?, modifier: Modifier = Modifier) {
    Box(modifier.background(Vivi.Teal50), contentAlignment = Alignment.Center) {
        if (url == null) {
            Image(painterResource(R.drawable.vivi_symbole), null, Modifier.size(40.dp), alpha = .25f)
        } else {
            var etatImage by remember(url) { mutableStateOf<coil.compose.AsyncImagePainter.State>(coil.compose.AsyncImagePainter.State.Empty) }
            if (etatImage is coil.compose.AsyncImagePainter.State.Loading || etatImage is coil.compose.AsyncImagePainter.State.Empty) {
                Squelette(Modifier.fillMaxSize(), androidx.compose.ui.graphics.RectangleShape)
            }
            if (etatImage is coil.compose.AsyncImagePainter.State.Error) {
                Image(painterResource(R.drawable.vivi_symbole), null, Modifier.size(40.dp), alpha = .25f)
            }
            AsyncImage(
                model = url, contentDescription = description, contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize(),
                onState = { etatImage = it },
            )
        }
    }
}

/** Marque : symbole + « VIVI » + « Appartements & salles. » (.vivi-brand) */
@Composable
fun Marque(modifier: Modifier = Modifier, blanc: Boolean = false, taille: Dp = 36.dp) {
    Row(modifier.semantics(mergeDescendants = true) {}, verticalAlignment = Alignment.CenterVertically) {
        Image(painterResource(if (blanc) R.drawable.vivi_symbole_blanc else R.drawable.vivi_symbole), contentDescription = null, modifier = Modifier.size(taille))
        Spacer(Modifier.width(8.dp))
        Text("VIVI", fontFamily = Outfit, fontWeight = FontWeight.ExtraBold, fontSize = (taille.value * .7f).sp, color = if (blanc) Vivi.White else Vivi.Teal900)
        Spacer(Modifier.width(6.dp))
        Text("Appartements\n& salles.", fontFamily = Outfit, fontWeight = FontWeight.Medium, fontSize = 11.sp, lineHeight = 12.sp,
            color = if (blanc) Vivi.Teal200 else Vivi.Teal700)
    }
}

/* ---------- Saisie ---------- */

/** Champ avec libellé visible au-dessus (jamais un simple texte indicatif) et erreur juste en dessous. */
@Composable
fun ChampTexte(
    valeur: String,
    onValeur: (String) -> Unit,
    libelle: String,
    modifier: Modifier = Modifier,
    indication: String? = null,
    aide: String? = null,
    erreur: String? = null,
    clavier: KeyboardType = KeyboardType.Text,
    lignes: Int = 1,
    actif: Boolean = true,
    icone: ImageVector? = null,
    prefixe: String? = null,
    transformation: androidx.compose.ui.text.input.VisualTransformation = androidx.compose.ui.text.input.VisualTransformation.None,
) {
    Column(modifier) {
        Text(libelle, style = MaterialTheme.typography.labelMedium, color = Vivi.Ink, modifier = Modifier.padding(bottom = 6.dp))
        OutlinedTextField(
            value = valeur,
            onValueChange = onValeur,
            modifier = Modifier.fillMaxWidth(),
            placeholder = indication?.let { { Text(it, color = Vivi.Muted) } },
            leadingIcon = icone?.let { { Icon(it, null, tint = Vivi.Muted) } },
            prefix = prefixe?.let { { Text(it, color = Vivi.Ink, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(end = 6.dp)) } },
            visualTransformation = transformation,
            isError = erreur != null,
            enabled = actif,
            singleLine = lignes == 1,
            minLines = lignes,
            keyboardOptions = KeyboardOptions(keyboardType = clavier),
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

/** Sélecteur segmenté (.segmented) : fond gris clair, choix actif en blanc et turquoise. */
@Composable
fun <T> Segmente(options: List<Pair<T, String>>, choisi: T?, onChoix: (T) -> Unit, modifier: Modifier = Modifier, desactives: Set<T> = emptySet()) {
    Row(
        modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(Vivi.Segmented).padding(4.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        options.forEach { (valeur, texte) ->
            val actif = valeur == choisi
            val desactive = valeur in desactives
            Box(
                Modifier
                    .weight(1f)
                    .heightIn(min = 44.dp)
                    .clip(RoundedCornerShape(9.dp))
                    .background(if (actif) Vivi.White else Color.Transparent)
                    .clickable(enabled = !desactive, role = Role.Tab) { onChoix(valeur) }
                    .semantics { stateDescription = if (actif) "sélectionné" else "" }
                    .padding(horizontal = 6.dp, vertical = 8.dp),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    texte, textAlign = TextAlign.Center, maxLines = 2, fontSize = 14.sp, fontWeight = FontWeight.SemiBold,
                    color = when { desactive -> Vivi.Muted.copy(alpha = .6f); actif -> Vivi.Teal800; else -> Vivi.Body },
                )
            }
        }
    }
}

/** Compteur − n + (.stepper), boutons ronds de 48 dp. */
@Composable
fun Compteur(valeur: Int, onValeur: (Int) -> Unit, min: Int, max: Int, libelle: String, modifier: Modifier = Modifier, suffixe: String = "", compact: Boolean = false, pas: Int = 1) {
    val taille = if (compact) 36.dp else 48.dp
    Row(modifier, verticalAlignment = Alignment.CenterVertically) {
        BoutonRond(Icons.Outlined.Remove, "Diminuer $libelle", valeur > min, taille) { onValeur((valeur - pas).coerceAtLeast(min)) }
        Text(
            "$valeur$suffixe", modifier = Modifier.widthIn(min = if (compact) 32.dp else 44.dp).semantics { contentDescription = "$libelle : $valeur$suffixe" },
            textAlign = TextAlign.Center, fontWeight = FontWeight.Bold, color = Vivi.Ink, fontSize = if (compact) 15.sp else 17.sp,
        )
        BoutonRond(Icons.Outlined.Add, "Augmenter $libelle", valeur < max, taille) { onValeur((valeur + pas).coerceAtMost(max)) }
    }
}

@Composable
private fun BoutonRond(icone: ImageVector, description: String, actif: Boolean, taille: Dp = 48.dp, onClick: () -> Unit) {
    Box(
        Modifier.size(taille).clip(CircleShape)
            .border(1.5.dp, if (actif) Vivi.LineStrong else Vivi.Line, CircleShape)
            .background(Vivi.White)
            .clickable(enabled = actif, role = Role.Button, onClick = onClick)
            .semantics { contentDescription = description },
        contentAlignment = Alignment.Center,
    ) { Icon(icone, null, tint = if (actif) Vivi.Ink else Vivi.LineStrong) }
}

/* ---------- États d'écran ---------- */

@Composable
fun Chargement(modifier: Modifier = Modifier, texte: String? = null) {
    Column(modifier.fillMaxSize().padding(32.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
        CircularProgressIndicator(color = Vivi.Teal700, strokeWidth = 3.dp)
        if (texte != null) {
            Spacer(Modifier.height(14.dp))
            Text(texte, style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted, textAlign = TextAlign.Center)
        }
    }
}

@Composable
fun EtatVide(icone: ImageVector, titre: String, texte: String? = null, modifier: Modifier = Modifier, action: (@Composable () -> Unit)? = null) {
    Column(modifier.fillMaxWidth().padding(horizontal = 24.dp, vertical = 40.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Box(Modifier.size(72.dp).clip(CircleShape).background(Vivi.Teal50), contentAlignment = Alignment.Center) {
            Icon(icone, null, tint = Vivi.Teal700, modifier = Modifier.size(34.dp))
        }
        Spacer(Modifier.height(16.dp))
        Text(titre, style = MaterialTheme.typography.titleLarge, textAlign = TextAlign.Center)
        if (texte != null) {
            Spacer(Modifier.height(6.dp))
            Text(texte, style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted, textAlign = TextAlign.Center)
        }
        if (action != null) {
            Spacer(Modifier.height(20.dp))
            action()
        }
    }
}

@Composable
fun EtatErreur(message: String, horsLigne: Boolean = false, onReessayer: () -> Unit, modifier: Modifier = Modifier) {
    EtatVide(
        icone = if (horsLigne) Icons.Outlined.CloudOff else Icons.Outlined.ErrorOutline,
        titre = if (horsLigne) "Hors connexion" else "Impossible d’afficher cette page",
        texte = message,
        modifier = modifier,
    ) { BoutonSecondaire("Réessayer", onReessayer) }
}

/** Bandeau d'information ou d'erreur dans le flux de la page (.alert). */
@Composable
fun Bandeau(texte: String, modifier: Modifier = Modifier, ton: TonPuce = TonPuce.Teal, icone: ImageVector? = null) {
    Row(
        modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(ton.fond).padding(14.dp),
        verticalAlignment = Alignment.Top,
    ) {
        if (icone != null) {
            Icon(icone, null, tint = ton.texte, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(10.dp))
        }
        Text(texte, color = ton.texte, style = MaterialTheme.typography.bodyMedium.copy(color = ton.texte))
    }
}

/* ---------- Barres ---------- */

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BarreTitre(
    titre: String,
    onRetour: (() -> Unit)? = null,
    actions: @Composable RowScope.() -> Unit = {},
    defilement: TopAppBarScrollBehavior? = null,
) {
    CenterAlignedTopAppBar(
        title = { Text(titre, style = MaterialTheme.typography.titleMedium, maxLines = 1, overflow = TextOverflow.Ellipsis) },
        navigationIcon = {
            if (onRetour != null) {
                IconButton(onClick = onRetour) { Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Retour", tint = Vivi.Ink) }
            }
        },
        actions = actions,
        scrollBehavior = defilement,
        colors = TopAppBarDefaults.centerAlignedTopAppBarColors(containerColor = Vivi.White, scrolledContainerColor = Vivi.White),
    )
}

/** Barre d'action fixée en bas (prix + bouton), au-dessus de la barre de navigation système. */
@Composable
fun BarreAction(modifier: Modifier = Modifier, contenu: @Composable RowScope.() -> Unit) {
    Surface(color = Vivi.White, shadowElevation = 12.dp, modifier = modifier.fillMaxWidth()) {
        Column {
            Separateur()
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                content = contenu,
            )
        }
    }
}

/**
 * Affiche un numéro béninois par paires (« 0197000000 » → « 01 97 00 00 00 ») ;
 * le champ ne garde que les chiffres (copier-coller, envoi au serveur).
 */
object FormatNumeroBenin : androidx.compose.ui.text.input.VisualTransformation {
    override fun filter(text: androidx.compose.ui.text.AnnotatedString): androidx.compose.ui.text.input.TransformedText {
        val brut = text.text
        val affiche = brut.chunked(2).joinToString(" ")
        val decalage = object : androidx.compose.ui.text.input.OffsetMapping {
            // Une espace est ajoutée après chaque paire complète suivie d'autres chiffres
            override fun originalToTransformed(offset: Int): Int =
                (offset + (offset - 1).coerceAtLeast(0) / 2).coerceAtMost(affiche.length)
            override fun transformedToOriginal(offset: Int): Int =
                (offset - offset / 3).coerceIn(0, brut.length)
        }
        return androidx.compose.ui.text.input.TransformedText(androidx.compose.ui.text.AnnotatedString(affiche), decalage)
    }
}
