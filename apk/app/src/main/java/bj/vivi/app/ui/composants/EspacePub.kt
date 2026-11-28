package bj.vivi.app.ui.composants

import android.content.Context
import android.graphics.Typeface
import android.provider.Settings
import android.text.TextUtils
import android.util.Log
import android.view.ViewGroup
import android.view.accessibility.AccessibilityManager
import android.widget.Button
import android.widget.LinearLayout
import android.widget.TextView
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.IconButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.boundsInWindow
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle
import bj.vivi.app.data.Pub
import bj.vivi.app.data.Publicites
import bj.vivi.app.data.RegieAdmob
import bj.vivi.app.data.Reseau
import bj.vivi.app.ui.theme.Vivi
import coil.compose.AsyncImage
import com.google.android.gms.ads.AdListener
import com.google.android.gms.ads.AdLoader
import com.google.android.gms.ads.AdRequest
import com.google.android.gms.ads.AdSize
import com.google.android.gms.ads.AdView
import com.google.android.gms.ads.LoadAdError
import com.google.android.gms.ads.nativead.MediaView
import com.google.android.gms.ads.nativead.NativeAd
import com.google.android.gms.ads.nativead.NativeAdView
import kotlinx.coroutines.delay

/*
 * Espaces publicitaires (docs/API-mobile.md § Publicité). Ordre : annonces de VIVI, sinon bloc AdMob, sinon rien.
 * Ne jamais placer d'EspacePub sur la connexion, le paiement, le billet, le scanner ni dans les espaces pro et agent.
 */

/** Ouvre l'annonce touchée : écran de l'application (lien interne) ou navigateur. Fourni par AppVivi. */
val LocalOuvrirPub = staticCompositionLocalOf<(Publicites.Annonce) -> Unit> { {} }

/** Affichage d'un écran : chaque annonce n'y compte qu'une vue, et les annonces natives AdMob n'y sont chargées qu'une fois. */
class SuiviPub {
    private val vues = mutableSetOf<Long>()
    internal val natives = mutableStateMapOf<String, NativeAd>()
    internal val demandees = mutableSetOf<String>()
    internal var ferme = false

    fun vue(id: Long) { if (vues.add(id)) Pub.vue(id) }

    internal fun liberer() {
        ferme = true
        natives.values.forEach { it.destroy() }
        natives.clear()
    }
}

/** À placer une fois par écran, hors des listes défilantes. */
@Composable
fun rememberSuiviPub(): SuiviPub {
    val suivi = remember { SuiviPub() }
    DisposableEffect(suivi) { onDispose { suivi.liberer() } }
    return suivi
}

/** [rang] : place de l'espace quand il se répète dans une liste (chaque répétition commence par une autre annonce). */
@Composable
fun EspacePub(cle: String, suivi: SuiviPub, modifier: Modifier = Modifier, rang: Int = 0) {
    val utilisateur by Reseau.session.utilisateur.collectAsState()
    if (Pub.interdite(utilisateur)) return
    val e = Pub.emplacement(cle) ?: return
    val bloc = e.admob
    when {
        e.annonces.isNotEmpty() -> Carrousel(e, rang, suivi, modifier)
        bloc == null || !RegieAdmob.prete -> Unit
        bloc.format == "natif" -> AnnonceNative("$cle-$rang", bloc.bloc, suivi, modifier)
        bloc.format == "banniere_adaptative" -> BanniereAdmob(bloc.bloc, modifier)
    }
}

/* ---------- Annonces de VIVI ---------- */

@Composable
private fun Carrousel(e: Publicites.Emplacement, rang: Int, suivi: SuiviPub, modifier: Modifier) {
    val annonces = remember(e.annonces, rang) { e.annonces.indices.map { e.annonces[(it + rang) % e.annonces.size] } }
    val pages = rememberPagerState { annonces.size }
    var visible by remember { mutableStateOf(false) }
    val cycle = LocalLifecycleOwner.current.lifecycle
    val defileSeul = defilementAutomatique()

    // Vue : au moins la moitié de l'annonce visible pendant une seconde, écran au premier plan
    LaunchedEffect(visible, pages.settledPage, Pub.masquee, annonces) {
        val a = annonces.getOrNull(pages.settledPage)
        if (!visible || Pub.masquee || a == null) return@LaunchedEffect
        cycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            delay(1_000)
            suivi.vue(a.id)
            if (annonces.size > 1 && defileSeul) {
                delay(6_000)
                if (!pages.isScrollInProgress) pages.animateScrollToPage((pages.settledPage + 1) % annonces.size)
            }
        }
    }

    Column(
        modifier.fillMaxWidth().onGloballyPositioned { c ->
            val vu = c.boundsInWindow()
            val total = c.size.width.toFloat() * c.size.height
            visible = total > 0 && vu.width * vu.height >= total / 2
        },
    ) {
        HorizontalPager(pages, Modifier.fillMaxWidth(), pageSpacing = 12.dp, verticalAlignment = Alignment.Top) { i ->
            annonces.getOrNull(i)?.let { CarteAnnonce(it, e) }
        }
        if (annonces.size > 1) {
            Row(Modifier.padding(top = 8.dp).align(Alignment.CenterHorizontally)) {
                repeat(annonces.size) { i ->
                    Box(Modifier.padding(horizontal = 3.dp).size(7.dp).clip(CircleShape).background(if (i == pages.currentPage) Vivi.Teal700 else Vivi.LineStrong))
                }
            }
        }
    }
}

/** Pas de défilement automatique si les animations sont coupées ou si un lecteur d'écran est actif. */
@Composable
private fun defilementAutomatique(): Boolean {
    val context = LocalContext.current
    return remember {
        val animations = Settings.Global.getFloat(context.contentResolver, Settings.Global.ANIMATOR_DURATION_SCALE, 1f) != 0f
        val lecteur = (context.getSystemService(Context.ACCESSIBILITY_SERVICE) as? AccessibilityManager)?.isTouchExplorationEnabled == true
        animations && !lecteur
    }
}

private fun mention(a: Publicites.Annonce) =
    listOfNotNull(Pub.donnees?.mention?.ifBlank { null } ?: "Publicité", a.annonceur?.ifBlank { null }).joinToString(" · ")

@Composable
private fun CarteAnnonce(a: Publicites.Annonce, e: Publicites.Emplacement) {
    val ouvrir = LocalOuvrirPub.current
    Carte(Modifier.fillMaxWidth(), onClick = if (a.lien != null) { { ouvrir(a) } } else null, remplissage = PaddingValues(0.dp)) {
        Column {
            a.image?.let {
                AsyncImage(
                    model = it, contentDescription = null, contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxWidth().aspectRatio(e.proportion).background(Vivi.Segmented),
                )
            }
            Row(Modifier.padding(horizontal = 14.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(mention(a), style = MaterialTheme.typography.bodySmall, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    if (a.titre.isNotBlank()) Text(a.titre, style = MaterialTheme.typography.titleSmall, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    a.texte?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall, maxLines = 2, overflow = TextOverflow.Ellipsis) }
                }
                if (a.lien != null) {
                    Spacer(Modifier.width(12.dp))
                    // Même forme que les boutons de l’application (turquoise, coins arrondis), sans flèche ; toute la carte est cliquable
                    Box(
                        Modifier.background(Vivi.Teal700, androidx.compose.foundation.shape.RoundedCornerShape(10.dp))
                            .padding(horizontal = 14.dp, vertical = 8.dp),
                    ) {
                        Text(a.bouton?.ifBlank { null } ?: "En savoir plus", color = Vivi.White, fontWeight = FontWeight.SemiBold, style = MaterialTheme.typography.labelLarge, maxLines = 1)
                    }
                }
            }
        }
    }
}

/** Annonce plein écran à l'ouverture de l'application, toujours fermable (bouton ou retour). */
@Composable
fun PubOuverture(a: Publicites.Annonce, onFermer: () -> Unit, onOuvrir: () -> Unit) {
    val suivi = rememberSuiviPub()
    LaunchedEffect(a.id) { delay(1_000); suivi.vue(a.id) }
    Dialog(onDismissRequest = onFermer, properties = DialogProperties(usePlatformDefaultWidth = false, decorFitsSystemWindows = false, dismissOnClickOutside = false)) {
        Box(Modifier.fillMaxSize().background(Vivi.Ink)) {
            a.image?.let {
                AsyncImage(model = it, contentDescription = a.titre.ifBlank { null }, contentScale = ContentScale.Fit, modifier = Modifier.fillMaxSize())
            }
            Row(Modifier.fillMaxWidth().statusBarsPadding().padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Puce(mention(a), TonPuce.Sombre)
                Spacer(Modifier.weight(1f))
                IconButton(onClick = onFermer, colors = IconButtonDefaults.iconButtonColors(containerColor = Vivi.White), modifier = Modifier.size(48.dp)) {
                    Icon(Icons.Outlined.Close, contentDescription = "Fermer la publicité", tint = Vivi.Ink)
                }
            }
            Column(Modifier.align(Alignment.BottomCenter).fillMaxWidth().background(Vivi.Ink.copy(alpha = .72f)).navigationBarsPadding().padding(20.dp)) {
                if (a.titre.isNotBlank()) Text(a.titre, style = MaterialTheme.typography.titleLarge.copy(color = Vivi.White))
                a.texte?.takeIf { it.isNotBlank() }?.let {
                    Spacer(Modifier.height(4.dp))
                    Text(it, style = MaterialTheme.typography.bodyMedium.copy(color = Vivi.White))
                }
                if (a.lien != null) {
                    Spacer(Modifier.height(14.dp))
                    BoutonPrincipal(a.bouton?.ifBlank { null } ?: "En savoir plus", onOuvrir, Modifier.fillMaxWidth())
                }
            }
        }
    }
}

/* ---------- Google AdMob ---------- */

/** Bannière adaptative : aucune place prise tant qu'aucune annonce n'est arrivée. */
@Composable
private fun BanniereAdmob(bloc: String, modifier: Modifier) {
    var arrivee by remember(bloc) { mutableStateOf(false) }
    var echec by remember(bloc) { mutableStateOf(false) }
    if (echec) return
    BoxWithConstraints(if (arrivee) modifier.fillMaxWidth() else Modifier.fillMaxWidth()) {
        // 32 dp : marges latérales que [modifier] ajoutera une fois l'annonce arrivée
        val largeur = remember(bloc) { (maxWidth.value.toInt() - 32).coerceAtLeast(280) }
        AndroidView(
            factory = { context ->
                AdView(context).apply {
                    setAdSize(AdSize.getCurrentOrientationAnchoredAdaptiveBannerAdSize(context, largeur))
                    adUnitId = bloc
                    adListener = object : AdListener() {
                        override fun onAdLoaded() { arrivee = true }
                        override fun onAdFailedToLoad(erreur: LoadAdError) {
                            Log.w("VIVI", "AdMob bannière : ${erreur.message}")
                            if (!arrivee) echec = true   // un renouvellement raté garde l'annonce déjà affichée
                        }
                    }
                    loadAd(AdRequest.Builder().build())
                }
            },
            modifier = if (arrivee) Modifier.align(Alignment.Center) else Modifier.height(0.dp),
            onRelease = { it.destroy() },
        )
    }
}

private const val MAX_NATIVES = 5

@Composable
private fun AnnonceNative(cle: String, bloc: String, suivi: SuiviPub, modifier: Modifier) {
    val context = LocalContext.current
    LaunchedEffect(cle, bloc) {
        if (suivi.demandees.size >= MAX_NATIVES || !suivi.demandees.add(cle)) return@LaunchedEffect
        AdLoader.Builder(context, bloc)
            .forNativeAd { if (suivi.ferme) it.destroy() else suivi.natives[cle] = it }
            .withAdListener(object : AdListener() {
                override fun onAdFailedToLoad(erreur: LoadAdError) { Log.w("VIVI", "AdMob natif : ${erreur.message}") }
            })
            .build().loadAd(AdRequest.Builder().build())
    }
    val annonce = suivi.natives[cle] ?: return
    Carte(modifier.fillMaxWidth(), remplissage = PaddingValues(0.dp)) {
        AndroidView(
            factory = { VueNative(it) }, update = { it.afficher(annonce) },
            modifier = Modifier.fillMaxWidth(), onRelease = { it.vue.destroy() },
        )
    }
}

/** Annonce native aux couleurs de VIVI ; chaque élément est déclaré à Google (titre, image, bouton…). */
private class VueNative(context: Context) : LinearLayout(context) {
    private val d = resources.displayMetrics.density
    private fun px(v: Int) = (v * d).toInt()
    val vue = NativeAdView(context)
    private val media = MediaView(context)
    private val annonceur = texte(12f, Vivi.Muted.toArgb())
    private val titre = texte(16f, Vivi.Ink.toArgb(), gras = true)
    private val corps = texte(13f, Vivi.Body.toArgb(), lignes = 2)
    private val bouton = Button(context).apply {
        isAllCaps = false
        setTextColor(Vivi.White.toArgb())
        textSize = 14f
        minHeight = px(48)
        background = android.graphics.drawable.GradientDrawable().apply { cornerRadius = px(14).toFloat(); setColor(Vivi.Teal700.toArgb()) }
    }

    private fun texte(taille: Float, couleur: Int, gras: Boolean = false, lignes: Int = 1) = TextView(context).apply {
        textSize = taille
        setTextColor(couleur)
        maxLines = lignes
        ellipsize = TextUtils.TruncateAt.END
        if (gras) setTypeface(typeface, Typeface.BOLD)
    }

    init {
        val contenu = LinearLayout(context).apply { orientation = VERTICAL }
        contenu.addView(media, LayoutParams(LayoutParams.MATCH_PARENT, px(190)))
        val bas = LinearLayout(context).apply { orientation = VERTICAL; setPadding(px(14), px(10), px(14), px(14)) }
        bas.addView(annonceur)
        bas.addView(titre)
        bas.addView(corps)
        bas.addView(bouton, LayoutParams(LayoutParams.MATCH_PARENT, LayoutParams.WRAP_CONTENT).apply { topMargin = px(10) })
        contenu.addView(bas)
        vue.addView(contenu, ViewGroup.LayoutParams(LayoutParams.MATCH_PARENT, LayoutParams.WRAP_CONTENT))
        vue.mediaView = media
        vue.advertiserView = annonceur
        vue.headlineView = titre
        vue.bodyView = corps
        vue.callToActionView = bouton
        addView(vue, LayoutParams(LayoutParams.MATCH_PARENT, LayoutParams.WRAP_CONTENT))
    }

    fun afficher(a: NativeAd) {
        annonceur.text = listOfNotNull("Annonce", a.advertiser ?: a.store).joinToString(" · ")
        titre.text = a.headline
        corps.text = a.body
        corps.visibility = if (a.body.isNullOrBlank()) GONE else VISIBLE
        bouton.text = a.callToAction
        bouton.visibility = if (a.callToAction.isNullOrBlank()) GONE else VISIBLE
        a.mediaContent?.let { media.mediaContent = it }
        vue.setNativeAd(a)
    }
}
