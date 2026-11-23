@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)

package bj.vivi.app.ui.client

import android.annotation.SuppressLint
import android.content.Intent
import android.graphics.Bitmap
import android.net.Uri
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.CloudOff
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import bj.vivi.app.data.Serveur
import bj.vivi.app.ui.composants.EtatVide
import bj.vivi.app.ui.composants.BoutonSecondaire
import bj.vivi.app.ui.theme.Vivi
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull

/**
 * Paiement FedaPay affiché DANS l'application (plus de navigateur externe) :
 * la page sécurisée de FedaPay s'ouvre dans une vue web ; à la fin, le serveur renvoie vers
 * vivi://paiement/{id}?statut=…, intercepté ici pour ouvrir le suivi du paiement (GET /paiements/{id}).
 * Les liens qui ne sont pas des pages web (USSD tel:, intent:, applications Mobile Money) partent vers leur application.
 */
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun EcranPaiementWeb(idPaiement: Long, onTermine: (Long) -> Unit, onQuitter: (Long) -> Unit) {
    val url = remember { PaiementsLances.url(idPaiement) }
    var progression by remember { mutableIntStateOf(0) }
    var hote by remember { mutableStateOf(url?.let { Uri.parse(it).host }.orEmpty()) }
    var securise by remember { mutableStateOf(url?.startsWith("https") == true) }
    var erreur by remember { mutableStateOf<String?>(null) }
    var confirmerSortie by remember { mutableStateOf(false) }
    var vue by remember { mutableStateOf<WebView?>(null) }
    var termine by remember { mutableStateOf(false) }

    /** Fin du paiement : une seule fois, même si la page renvoie plusieurs fois. */
    fun terminer(id: Long) {
        if (termine) return
        termine = true
        onTermine(id)
    }

    BackHandler {
        val v = vue
        if (v != null && v.canGoBack()) v.goBack() else confirmerSortie = true
    }

    if (url == null) {
        // Application relancée entre-temps : l'adresse de la page n'est plus connue, on suit le paiement
        androidx.compose.runtime.LaunchedEffect(Unit) { onQuitter(idPaiement) }
        return
    }

    Column(Modifier.fillMaxSize().background(Vivi.White)) {
        // ---------- Barre : fermer, page sécurisée, adresse ----------
        Row(Modifier.fillMaxWidth().statusBarsPadding().padding(horizontal = 4.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = { confirmerSortie = true }) { Icon(Icons.Outlined.Close, contentDescription = "Quitter le paiement", tint = Vivi.Ink) }
            Column(Modifier.weight(1f)) {
                Text("Paiement sécurisé", style = MaterialTheme.typography.titleMedium)
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Outlined.Lock, null, tint = if (securise) Vivi.Success else Vivi.Muted, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(hote.ifEmpty { "FedaPay" }, fontSize = 12.sp, color = Vivi.Muted, maxLines = 1)
                }
            }
            Text("FedaPay", fontWeight = FontWeight.Bold, color = Vivi.Teal700, modifier = Modifier.padding(end = 12.dp))
        }
        if (progression in 1..99) {
            LinearProgressIndicator(progress = { progression / 100f }, Modifier.fillMaxWidth().height(3.dp), color = Vivi.Teal700, trackColor = Vivi.Line, drawStopIndicator = {})
        } else Spacer(Modifier.height(3.dp))

        Box(Modifier.weight(1f).fillMaxWidth().navigationBarsPadding()) {
            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { context ->
                    WebView(context).apply {
                        vue = this
                        settings.javaScriptEnabled = true          // la page FedaPay en a besoin
                        settings.domStorageEnabled = true
                        settings.loadWithOverviewMode = true
                        settings.useWideViewPort = true
                        settings.setSupportMultipleWindows(false)
                        settings.allowFileAccess = false
                        settings.allowContentAccess = false
                        settings.mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
                        // Carte bancaire : la vérification de la banque (3-D Secure) s'affiche dans un cadre d'un autre site
                        android.webkit.CookieManager.getInstance().setAcceptThirdPartyCookies(this, true)
                        webChromeClient = object : WebChromeClient() {
                            override fun onProgressChanged(view: WebView, nouvelle: Int) { progression = nouvelle }
                        }
                        webViewClient = object : WebViewClient() {
                            override fun shouldOverrideUrlLoading(view: WebView, requete: WebResourceRequest): Boolean {
                                val lien = requete.url
                                return when (lien.scheme) {
                                    // Retour de FedaPay : vivi://paiement/{id}?statut=…
                                    "vivi" -> {
                                        val id = lien.lastPathSegment?.toLongOrNull() ?: idPaiement
                                        terminer(id)
                                        true
                                    }
                                    "http", "https" -> {
                                        // Les pages de paiement restent dans l'application ; retour « http:// » de la plateforme passé en HTTPS
                                        val sur = lien.toString().toHttpUrlOrNull()?.let { Serveur.securiser(it).toString() }
                                        if (lien.scheme == "http" && sur?.startsWith("https") == true) { view.loadUrl(sur); true } else false
                                    }
                                    else -> {
                                        // tel: (code USSD Mobile Money), intent:, application de l'opérateur
                                        runCatching {
                                            val intention = if (lien.scheme == "intent") Intent.parseUri(lien.toString(), Intent.URI_INTENT_SCHEME) else Intent(Intent.ACTION_VIEW, lien)
                                            context.startActivity(intention.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
                                        }
                                        true
                                    }
                                }
                            }

                            override fun onPageStarted(view: WebView, adresse: String?, icone: Bitmap?) {
                                val lien = adresse?.let { Uri.parse(it) } ?: return
                                // Redirection du serveur vers vivi://paiement/{id} vue comme une page : même traitement
                                if (lien.scheme == "vivi") { view.stopLoading(); terminer(lien.lastPathSegment?.toLongOrNull() ?: idPaiement); return }
                                erreur = null
                                hote = lien.host.orEmpty(); securise = lien.scheme == "https"
                            }

                            override fun onReceivedError(view: WebView, requete: WebResourceRequest, e: WebResourceError) {
                                if (requete.url.scheme == "vivi") { terminer(requete.url.lastPathSegment?.toLongOrNull() ?: idPaiement); return }
                                if (requete.isForMainFrame) erreur = e.description?.toString() ?: "Page indisponible."
                            }
                        }
                        loadUrl(url)
                    }
                },
                onRelease = { it.stopLoading(); it.destroy(); vue = null },
            )
            erreur?.let {
                Box(Modifier.fillMaxSize().background(Vivi.White), contentAlignment = Alignment.Center) {
                    EtatVide(Icons.Outlined.CloudOff, "Page de paiement indisponible", "Vérifiez votre connexion Internet. Aucun montant n’a été débité.") {
                        BoutonSecondaire("Réessayer", { erreur = null; vue?.reload() })
                    }
                }
            }
        }
    }

    if (confirmerSortie) {
        AlertDialog(
            onDismissRequest = { confirmerSortie = false },
            title = { Text("Quitter le paiement ?") },
            text = { Text("Si vous avez déjà validé sur votre téléphone (Mobile Money), le paiement sera confirmé dans quelques instants. Sinon, vous pourrez reprendre le paiement.") },
            confirmButton = { TextButton(onClick = { confirmerSortie = false; onQuitter(idPaiement) }) { Text("Quitter", color = Vivi.Danger, fontWeight = FontWeight.SemiBold) } },
            dismissButton = { TextButton(onClick = { confirmerSortie = false }) { Text("Continuer le paiement", color = Vivi.Teal700) } },
            containerColor = Vivi.White,
        )
    }
}
