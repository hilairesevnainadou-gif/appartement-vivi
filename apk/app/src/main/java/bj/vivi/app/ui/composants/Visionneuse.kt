package bj.vivi.app.ui.composants

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.awaitEachGesture
import androidx.compose.foundation.gestures.awaitFirstDown
import androidx.compose.foundation.gestures.calculatePan
import androidx.compose.foundation.gestures.calculateZoom
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.IconButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.input.pointer.positionChanged
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.IntSize
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import bj.vivi.app.data.Photo
import bj.vivi.app.ui.theme.Vivi
import coil.compose.AsyncImage
import kotlinx.coroutines.launch

private val FondVisionneuse = Color(0xFF061415)

/**
 * Visionneuse plein écran des photos d'un logement ou d'une salle (même principe que sur le site) :
 * glisser pour changer de photo, pincer ou toucher deux fois pour zoomer, compteur, vignettes,
 * fermeture par la croix ou le bouton retour du téléphone.
 */
@Composable
fun Visionneuse(photos: List<Photo>, depart: Int, titre: String, onFermer: () -> Unit) {
    Dialog(
        onDismissRequest = onFermer,
        properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false),
    ) {
        val pages = rememberPagerState(initialPage = depart.coerceIn(0, (photos.size - 1).coerceAtLeast(0))) { photos.size }
        val portee = rememberCoroutineScope()
        val vignettes = rememberLazyListState()
        // Zoom de la photo affichée ; le glissement entre photos est bloqué tant qu'elle est agrandie
        var zoome by remember { mutableStateOf(false) }
        LaunchedEffect(pages.currentPage) { if (photos.size > 1) vignettes.animateScrollToItem(pages.currentPage) }

        Column(Modifier.fillMaxSize().background(FondVisionneuse)) {
            // ---------- Haut : titre, compteur, fermer ----------
            Row(
                Modifier.fillMaxWidth().statusBarsPadding().padding(horizontal = 16.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                Text(titre, Modifier.weight(1f), color = Vivi.White, style = MaterialTheme.typography.titleSmall, maxLines = 1)
                Text("${pages.currentPage + 1} / ${photos.size}", color = Vivi.White.copy(alpha = .75f), style = MaterialTheme.typography.bodyMedium)
                IconButton(
                    onClick = onFermer,
                    modifier = Modifier.size(44.dp).border(BorderStroke(1.5.dp, Vivi.White.copy(alpha = .35f)), RoundedCornerShape(12.dp)),
                    colors = IconButtonDefaults.iconButtonColors(containerColor = Vivi.White.copy(alpha = .08f)),
                ) { Icon(Icons.Outlined.Close, contentDescription = "Fermer", tint = Vivi.White) }
            }

            // ---------- Photo ----------
            HorizontalPager(
                pages, Modifier.weight(1f).fillMaxWidth(),
                userScrollEnabled = !zoome, beyondViewportPageCount = 1, key = { photos[it].url ?: it },
            ) { i ->
                PhotoZoomable(
                    photo = photos[i],
                    description = photos[i].legende ?: "Photo ${i + 1} sur ${photos.size}, $titre",
                    active = i == pages.currentPage,
                    onZoom = { if (i == pages.currentPage) zoome = it },
                )
            }

            // ---------- Légende et vignettes ----------
            photos.getOrNull(pages.currentPage)?.legende?.takeIf { it.isNotBlank() }?.let {
                Text(it, Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp), color = Vivi.White.copy(alpha = .8f),
                    style = MaterialTheme.typography.bodyMedium, textAlign = TextAlign.Center)
            }
            if (photos.size > 1) {
                LazyRow(
                    Modifier.fillMaxWidth().navigationBarsPadding().padding(vertical = 12.dp),
                    state = vignettes,
                    contentPadding = PaddingValues(horizontal = 16.dp),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    itemsIndexed(photos) { i, p ->
                        val actuelle = i == pages.currentPage
                        AsyncImage(
                            model = p.url, contentDescription = "Photo ${i + 1}", contentScale = ContentScale.Crop,
                            modifier = Modifier.width(64.dp).height(48.dp).clip(RoundedCornerShape(8.dp))
                                .border(2.dp, if (actuelle) Vivi.Orange400 else Color.Transparent, RoundedCornerShape(8.dp))
                                .graphicsLayer { alpha = if (actuelle) 1f else .55f }
                                .clickable { portee.launch { pages.animateScrollToPage(i) } },
                        )
                    }
                }
            } else {
                Box(Modifier.navigationBarsPadding().height(12.dp))
            }
        }
    }
}

/** Une photo entière (sans recadrage), agrandissable au pincement ou par double toucher, déplaçable une fois agrandie. */
@Composable
private fun PhotoZoomable(photo: Photo, description: String, active: Boolean, onZoom: (Boolean) -> Unit) {
    var echelle by remember { mutableFloatStateOf(1f) }
    var decalage by remember { mutableStateOf(Offset.Zero) }
    var taille by remember { mutableStateOf(IntSize.Zero) }

    // Photo quittée : elle revient à sa taille normale
    LaunchedEffect(active) { if (!active) { echelle = 1f; decalage = Offset.Zero } }

    fun borner(o: Offset): Offset {
        val maxX = taille.width * (echelle - 1f) / 2f
        val maxY = taille.height * (echelle - 1f) / 2f
        return Offset(o.x.coerceIn(-maxX, maxX), o.y.coerceIn(-maxY, maxY))
    }

    Box(
        Modifier.fillMaxSize()
            .onSizeChanged { taille = it }
            // Pincement (deux doigts) ou déplacement d'une photo agrandie ; sinon le glissement change de photo
            .pointerInput(Unit) {
                awaitEachGesture {
                    awaitFirstDown(requireUnconsumed = false)
                    do {
                        val evenement = awaitPointerEvent()
                        if (evenement.changes.size > 1 || echelle > 1f) {
                            echelle = (echelle * evenement.calculateZoom()).coerceIn(1f, 4f)
                            decalage = if (echelle <= 1f) Offset.Zero else borner(decalage + evenement.calculatePan())
                            onZoom(echelle > 1f)
                            evenement.changes.forEach { if (it.positionChanged()) it.consume() }
                        }
                    } while (evenement.changes.any { it.pressed })
                }
            }
            .pointerInput(Unit) {
                detectTapGestures(onDoubleTap = { point ->
                    if (echelle > 1f) { echelle = 1f; decalage = Offset.Zero } else {
                        echelle = 2.5f
                        // Agrandit autour du point touché
                        decalage = borner(Offset((taille.width / 2f - point.x) * 1.5f, (taille.height / 2f - point.y) * 1.5f))
                    }
                    onZoom(echelle > 1f)
                })
            },
        contentAlignment = Alignment.Center,
    ) {
        AsyncImage(
            model = photo.url, contentDescription = description, contentScale = ContentScale.Fit,
            modifier = Modifier.fillMaxSize().graphicsLayer {
                scaleX = echelle; scaleY = echelle
                translationX = decalage.x; translationY = decalage.y
            },
        )
    }
}
