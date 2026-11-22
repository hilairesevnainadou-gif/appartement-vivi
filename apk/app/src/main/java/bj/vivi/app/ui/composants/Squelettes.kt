package bj.vivi.app.ui.composants

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import bj.vivi.app.ui.theme.Vivi

/*
 * Squelettes de chargement : la forme de l'écran apparaît tout de suite, avec un reflet qui glisse,
 * au lieu d'un simple rond qui tourne. Lus « Chargement en cours » par TalkBack.
 */

@Composable
private fun brosseReflet(): Brush {
    val transition = rememberInfiniteTransition(label = "reflet")
    val x by transition.animateFloat(
        initialValue = -400f, targetValue = 1400f,
        animationSpec = infiniteRepeatable(tween(1100, easing = LinearEasing), RepeatMode.Restart), label = "x",
    )
    return Brush.linearGradient(
        colors = listOf(Vivi.Segmented, Vivi.Line.copy(alpha = .55f), Vivi.Segmented),
        start = Offset(x, 0f), end = Offset(x + 400f, 260f),
    )
}

/** Bloc gris animé (texte, photo, bouton). */
@Composable
fun Squelette(modifier: Modifier = Modifier, forme: Shape = RoundedCornerShape(8.dp)) {
    Box(modifier.clip(forme).background(brosseReflet()))
}

@Composable
private fun Ligne(largeur: Float, hauteur: Dp = 14.dp) = Squelette(Modifier.fillMaxWidth(largeur).height(hauteur))

/** Carte de logement (photo 4:3, lieu, titre, puces, prix). */
@Composable
fun SqueletteCarteLogement(modifier: Modifier = Modifier) {
    Carte(modifier.fillMaxWidth(), remplissage = PaddingValues(0.dp)) {
        Column {
            Squelette(Modifier.fillMaxWidth().aspectRatio(4f / 3f), RoundedCornerShape(0.dp))
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Ligne(.45f, 12.dp)
                Ligne(.75f, 20.dp)
                Ligne(.55f, 12.dp)
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    repeat(3) { Squelette(Modifier.width(76.dp).height(28.dp), CircleShape) }
                }
                Ligne(.35f, 22.dp)
            }
        }
    }
}

/** Liste de cartes « vignette + trois lignes » (réservations, notifications, listes pro). */
@Composable
fun SqueletteListe(modifier: Modifier = Modifier, nombre: Int = 5) {
    Column(
        modifier.fillMaxSize().padding(16.dp).semantics { contentDescription = "Chargement en cours" },
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        repeat(nombre) {
            Carte(Modifier.fillMaxWidth()) {
                Row {
                    Squelette(Modifier.size(64.dp), RoundedCornerShape(12.dp))
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Squelette(Modifier.width(110.dp).height(22.dp), CircleShape)
                        Ligne(.85f, 16.dp)
                        Ligne(.6f, 12.dp)
                    }
                }
            }
        }
    }
}

/** Fiche logement : grande photo, titre, caractéristiques, tarifs. */
@Composable
fun SqueletteFiche(modifier: Modifier = Modifier) {
    Column(modifier.fillMaxSize().verticalScroll(rememberScrollState(), enabled = false).semantics { contentDescription = "Chargement en cours" }) {
        Squelette(Modifier.fillMaxWidth().aspectRatio(4f / 3f), RoundedCornerShape(0.dp))
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row {
                Squelette(Modifier.width(140.dp).height(28.dp), CircleShape)
                Spacer(Modifier.weight(1f))
                Squelette(Modifier.width(70.dp).height(28.dp), CircleShape)
            }
            Ligne(.8f, 28.dp)
            Ligne(.5f, 14.dp)
            Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) { repeat(3) { Squelette(Modifier.width(90.dp).height(18.dp)) } }
            Spacer(Modifier.height(8.dp))
            Ligne(.45f, 22.dp)
            Squelette(Modifier.fillMaxWidth().height(170.dp), RoundedCornerShape(16.dp))
            Ligne(1f); Ligne(.9f); Ligne(.7f)
        }
    }
}

/** Page de détail (billet, tunnel, tableau de bord) : en-tête puis blocs. */
@Composable
fun SqueletteDetail(modifier: Modifier = Modifier) {
    Column(
        modifier.fillMaxSize().padding(16.dp).semantics { contentDescription = "Chargement en cours" },
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Squelette(Modifier.fillMaxWidth().height(56.dp), RoundedCornerShape(12.dp))
        Carte(Modifier.fillMaxWidth()) {
            Row {
                Squelette(Modifier.size(72.dp), RoundedCornerShape(12.dp))
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) { Ligne(.8f, 18.dp); Ligne(.6f); Ligne(.4f, 12.dp) }
            }
        }
        repeat(2) {
            Carte(Modifier.fillMaxWidth()) {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Ligne(.4f, 20.dp)
                    Squelette(Modifier.fillMaxWidth().height(48.dp), RoundedCornerShape(12.dp))
                    Squelette(Modifier.fillMaxWidth().height(48.dp), RoundedCornerShape(12.dp))
                }
            }
        }
    }
}

/** Grand en-tête sombre (accueil pro) suivi de blocs. */
@Composable
fun SqueletteTableauDeBord(modifier: Modifier = Modifier) {
    Column(modifier.fillMaxSize().semantics { contentDescription = "Chargement en cours" }) {
        Box(Modifier.fillMaxWidth().background(Vivi.Teal900).statusBarsPadding().padding(16.dp).height(140.dp))
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                repeat(2) { Squelette(Modifier.weight(1f).height(90.dp), RoundedCornerShape(16.dp)) }
            }
            repeat(3) { Squelette(Modifier.fillMaxWidth().height(76.dp), RoundedCornerShape(16.dp)) }
        }
    }
}
