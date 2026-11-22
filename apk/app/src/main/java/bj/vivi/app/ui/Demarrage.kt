package bj.vivi.app.ui

import android.provider.Settings
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import bj.vivi.app.R
import bj.vivi.app.ui.theme.Outfit
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

/**
 * Animation d'ouverture (environ 1,9 s), enchaînée sans coupure après l'écran de démarrage
 * d'Android (même fond turquoise foncé, même symbole blanc au centre) :
 *  1. une onde orange part du symbole, qui rebondit et remonte ;
 *  2. « VIVI » apparaît en se resserrant, puis « Appartements & salles. » ;
 *  3. une barre orange se remplit en bas ;
 *  4. l'écran s'efface en zoomant légèrement sur l'application.
 * Si les animations du téléphone sont coupées (accessibilité), il disparaît aussitôt.
 */
@Composable
fun EcranDemarrage(onFini: () -> Unit) {
    val contexte = LocalContext.current
    val sansAnimation = remember {
        Settings.Global.getFloat(contexte.contentResolver, Settings.Global.ANIMATOR_DURATION_SCALE, 1f) == 0f
    }
    val densite = LocalDensity.current

    val symboleEchelle = remember { Animatable(1f) }
    val symboleMontee = remember { Animatable(0f) }      // en dp, vers le haut
    val onde = remember { Animatable(0f) }
    val titre = remember { Animatable(0f) }
    val slogan = remember { Animatable(0f) }
    val barre = remember { Animatable(0f) }
    val sortie = remember { Animatable(0f) }

    LaunchedEffect(Unit) {
        if (sansAnimation) { onFini(); return@LaunchedEffect }
        // Attendre que l'affichage soit fluide : au démarrage, les premières images arrivent en retard
        // (chargement de l'application) et l'animation, réglée sur le temps, serait en partie sautée.
        var precedente = androidx.compose.runtime.withFrameNanos { it }
        val debut = precedente
        var fluides = 0
        while (fluides < 4 && precedente - debut < 600_000_000L) {   // 0,6 s au plus (téléphone lent)
            val t = androidx.compose.runtime.withFrameNanos { it }
            fluides = if (t - precedente < 34_000_000L) fluides + 1 else 0   // < 34 ms entre deux images
            precedente = t
        }
        coroutineScope {
            launch { onde.animateTo(1f, tween(900, easing = FastOutSlowInEasing)) }
            launch {
                symboleEchelle.animateTo(0.86f, tween(140))
                symboleEchelle.animateTo(1f, spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessMediumLow))
            }
            launch { delay(120); symboleMontee.animateTo(46f, spring(dampingRatio = 0.7f, stiffness = Spring.StiffnessLow)) }
            launch { delay(300); titre.animateTo(1f, tween(520, easing = FastOutSlowInEasing)) }
            launch { delay(620); slogan.animateTo(1f, tween(420)) }
            launch { delay(200); barre.animateTo(1f, tween(1250, easing = LinearEasing)) }
        }
        delay(150)
        sortie.animateTo(1f, tween(380, easing = FastOutSlowInEasing))
        onFini()
    }

    Box(
        Modifier.fillMaxSize()
            .graphicsLayer { alpha = 1f - sortie.value; val e = 1f + .08f * sortie.value; scaleX = e; scaleY = e }
            .background(Vivi.Teal900)
            .clearAndSetSemantics { contentDescription = "VIVI, ouverture de l’application" },
        contentAlignment = Alignment.Center,
    ) {
        // Onde orange autour du symbole
        Box(
            Modifier.size(120.dp)
                .graphicsLayer {
                    translationY = -symboleMontee.value * densite.density
                    val e = .6f + 1.8f * onde.value; scaleX = e; scaleY = e
                    alpha = (1f - onde.value) * .45f
                }
                .clip(CircleShape).background(Vivi.Orange400),
        )
        // Symbole (même taille et même place que celui de l'écran de démarrage d'Android)
        Image(
            painterResource(R.drawable.vivi_symbole_blanc), null,
            Modifier.size(96.dp).graphicsLayer {
                translationY = -symboleMontee.value * densite.density
                scaleX = symboleEchelle.value; scaleY = symboleEchelle.value
            },
        )
        // Nom et slogan, sous le symbole remonté
        Column(
            Modifier.padding(top = 150.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(
                "VIVI", fontFamily = Outfit, fontWeight = FontWeight.ExtraBold, fontSize = 40.sp, color = Vivi.White,
                letterSpacing = (2f + 14f * (1f - titre.value)).sp,
                modifier = Modifier.graphicsLayer { alpha = titre.value; translationY = (1f - titre.value) * 18.dp.toPx() },
            )
            Spacer(Modifier.height(4.dp))
            Text(
                "Appartements & salles.", fontFamily = Outfit, fontWeight = FontWeight.Medium, fontSize = 16.sp, color = Vivi.Teal200,
                modifier = Modifier.graphicsLayer { alpha = slogan.value; translationY = (1f - slogan.value) * 10.dp.toPx() },
            )
        }
        // Barre de chargement orange
        Box(
            Modifier.align(Alignment.BottomCenter).navigationBarsPadding().padding(bottom = 56.dp)
                .width(120.dp).height(4.dp).clip(RoundedCornerShape(2.dp)).background(Vivi.White.copy(alpha = .15f)),
        ) {
            Box(Modifier.fillMaxWidth(barre.value).height(4.dp).clip(RoundedCornerShape(2.dp)).background(Vivi.Orange400))
        }
    }
}
