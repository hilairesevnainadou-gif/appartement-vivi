package bj.vivi.app.ui.compte

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.Chat
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.Email
import androidx.compose.material.icons.outlined.EventRepeat
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.ReportProblem
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import bj.vivi.app.data.Catalogue
import bj.vivi.app.data.Configuration
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.composants.Bandeau
import bj.vivi.app.ui.composants.BarreTitre
import bj.vivi.app.ui.composants.BoutonContour
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.Puce
import bj.vivi.app.ui.composants.Segmente
import bj.vivi.app.ui.composants.Separateur
import bj.vivi.app.ui.composants.SqueletteDetail
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.ouvrirLien
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.launch

/**
 * Pages d'aide affichées dans l'application (plus de passage par le navigateur) :
 * « Annulation et report » et « Conditions générales ». Même texte que le site
 * (plateforme/resources/views/pages/aide), chiffres lus dans GET /configuration.
 */
object OngletAide {
    const val ANNULATION = "annulation"
    const val CONDITIONS = "conditions"
}

@OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
@Composable
fun EcranAide(ongletInitial: String, onRetour: () -> Unit) {
    var onglet by rememberSaveable { mutableStateOf(ongletInitial) }
    var config by remember { mutableStateOf(Catalogue.configuration) }
    LaunchedEffect(Unit) { if (config == null) config = Catalogue.obtenir() }

    Scaffold(containerColor = Vivi.Sand, topBar = { BarreTitre("Aide", onRetour) }) { marges ->
        Column(Modifier.padding(marges).fillMaxSize()) {
            Segmente(
                listOf(OngletAide.ANNULATION to "Annulation et report", OngletAide.CONDITIONS to "Conditions générales"),
                onglet, { onglet = it }, Modifier.padding(horizontal = 16.dp, vertical = 12.dp),
            )
            val c = config
            if (c == null) {
                SqueletteDetail()
            } else key(onglet) {
                if (onglet == OngletAide.ANNULATION) PageAnnulation(c) else PageConditions(c) { onglet = OngletAide.ANNULATION }
            }
        }
    }
}

private fun taux(c: Configuration) = Fmt.taux(c.commissionPourcentage)

/** « jusqu’à 100 000 FCFA : 10 % ; au-delà : 5 % » (salles), ou null si le serveur n'a pas de tranches. */
private fun tranchesSalles(c: Configuration) = c.commissionSallesTranches.takeIf { it.isNotEmpty() }?.joinToString(" ; ") { t ->
    (t.jusqua?.let { "jusqu’à ${Fmt.fcfa(it)}" } ?: "au-delà") + " : ${Fmt.taux(t.pourcentage)} %"
}

/* ---------- Annulation et report ---------- */

@Composable
private fun PageAnnulation(c: Configuration) {
    LazyColumn(contentPadding = PaddingValues(start = 16.dp, end = 16.dp, bottom = 24.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        item {
            Text("Ce que vous récupérez si vous annulez, et comment changer de date. Le montant exact est toujours affiché avant de confirmer une annulation.",
                style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)
        }
        item {
            Bandeau("La commission VIVI (${taux(c)} % pour un logement${tranchesSalles(c)?.let { ", selon le montant pour une salle" } ?: ""}) n’est jamais remboursée. La location et les services à la carte le sont selon les règles ci-dessous.",
                ton = TonPuce.Orange, icone = Icons.Outlined.Info)
        }
        item {
            Bareme(
                Icons.Outlined.Schedule, "À l’heure et à la demi-journée", "Politique « Flexible »",
                listOf(
                    LigneBareme("24 h ou plus avant l’arrivée", "Remboursée", true),
                    LigneBareme("Moins de 24 h avant l’arrivée", "Due", false),
                ),
            )
        }
        item {
            Bareme(
                Icons.Outlined.CalendarMonth, "À la journée", "Politique « Modérée »",
                listOf(
                    LigneBareme("5 jours ou plus avant l’arrivée", "Remboursée", true),
                    LigneBareme("Moins de 5 jours avant l’arrivée", "1re journée et moitié des suivantes dues", null),
                ),
                exemple = "Exemple : 3 journées à 25 000 FCFA annulées 2 jours avant. Restent dus 25 000 + 12 500 + 12 500 = 50 000 FCFA ; 25 000 FCFA de location vous sont rendus, plus les services.",
            )
        }
        item {
            Section(Icons.Outlined.ReportProblem, "Cas particuliers") {
                Puceur("Heure d’arrivée passée", " ou client absent : la réservation est consommée et n’est plus remboursable.")
                Puceur("Réservation déjà reportée", " : elle n’est plus remboursable, quel que soit le délai.")
                Puceur("Versement", " : sur le compte Mobile Money ou la carte utilisés pour payer, sous 24 h à 7 jours. Vous êtes prévenu par SMS ou email.")
            }
        }
        item {
            Section(Icons.Outlined.EventRepeat, "Reporter plutôt qu’annuler") {
                Puceur("Gratuit, une seule fois", ", jusqu’à ${c.reportDelaiHeures} h avant l’arrivée.")
                Puceur("Dans les ${c.reportFenetreJours} jours", " qui suivent la date prévue, dans le même logement et pour la même durée, selon ses disponibilités.")
                Puceur("Nouveau QR Code", " et nouveau code de secours envoyés ; l’ancien ne fonctionne plus.")
                Puceur("Moins de ${c.reportDelaiHeures} h avant l’arrivée", ", seuls le propriétaire ou VIVI peuvent encore déplacer la réservation.")
                Spacer(Modifier.height(4.dp))
                Text("Pour reporter ou annuler : Réservations › votre billet › Reporter ou Annuler.", style = MaterialTheme.typography.bodySmall)
            }
        }
        contact(c)
    }
}

private data class LigneBareme(val quand: String, val location: String, val rembourse: Boolean?)

@Composable
private fun Bareme(icone: ImageVector, titre: String, politique: String, lignes: List<LigneBareme>, exemple: String? = null) {
    Section(icone, titre, politique) {
        lignes.forEachIndexed { i, l ->
            if (i > 0) Separateur(Modifier.padding(vertical = 4.dp))
            Text("Vous annulez ${l.quand.replaceFirstChar { it.lowercase() }}", style = MaterialTheme.typography.titleSmall, color = Vivi.Ink)
            Spacer(Modifier.height(8.dp))
            @OptIn(ExperimentalLayoutApi::class)
            FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Puce("Location : ${l.location}", when (l.rembourse) { true -> TonPuce.Succes; false -> TonPuce.Danger; null -> TonPuce.Orange })
                Puce("Services : remboursés", TonPuce.Succes)
                Puce("Commission : conservée", TonPuce.Danger)
            }
        }
        exemple?.let {
            Spacer(Modifier.height(10.dp))
            Box(Modifier.fillMaxWidth().clip(MaterialTheme.shapes.small).background(Vivi.Sand).padding(12.dp)) {
                Text(it, style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}

/* ---------- Conditions générales ---------- */

private data class Article(val titre: String, val texte: String)

@Composable
private fun PageConditions(c: Configuration, versAnnulation: () -> Unit) {
    val articles = listOf(
        Article("Objet", "Appartement VIVI met en relation des clients et des propriétaires d’appartements meublés et de salles au Bénin, pour des séjours à l’heure, à la demi-journée ou à la journée, avec des services à la carte. Utiliser la plateforme vaut acceptation des présentes conditions."),
        Article("Compte et connexion", "La connexion se fait sans mot de passe, par un code à 6 chiffres envoyé par SMS ou par email. Ce code est personnel : ne le communiquez à personne, VIVI ne vous le demandera jamais. Vous êtes responsable de l’exactitude du numéro et de l’adresse renseignés."),
        Article("Réservation et prix", "Le prix affiché comprend la location et les services choisis. Une commission VIVI s’y ajoute : ${taux(c)} % pour un logement${tranchesSalles(c)?.let { " ; pour une salle, $it" } ?: ""}. Elle est indiquée avant le paiement. Pendant le paiement, le créneau vous est réservé ${c.blocageMinutes} minutes ; passé ce délai, il redevient disponible."),
        Article("Paiement", "Le paiement se fait en ligne par Mobile Money (MTN, Moov, Celtiis) ou par carte bancaire, sur la page sécurisée de notre prestataire FedaPay. VIVI ne conserve aucune donnée de carte. La réservation est confirmée dès la réception du paiement ; un reçu est disponible dans votre espace."),
        Article("Arrivée et séjour", "À la confirmation, vous recevez un QR Code et un code de secours à 6 chiffres. Présentez-les au propriétaire ou à son gestionnaire à l’arrivée. Respectez les horaires réservés, le nombre de voyageurs et les règles du logement."),
        Article("Annulation et report", "Les règles de remboursement et de report sont détaillées dans l’onglet « Annulation et report ». La commission VIVI n’est jamais remboursée. Le report est possible une fois, jusqu’à ${c.reportDelaiHeures} h avant l’arrivée."),
        Article("Propriétaires", "Pour publier, le propriétaire souscrit un abonnement mensuel ; ses logements ne sont visibles que tant que l’abonnement est en cours. Il garantit l’exactitude des annonces, la propreté et la sécurité du logement. Chaque séjour démarré (QR Code scanné) crédite son solde ; ses retraits sont validés par la caisse VIVI."),
        Article("Données personnelles", "VIVI collecte le nom, le téléphone, l’email et l’historique des réservations, uniquement pour gérer les réservations, les paiements et le service client. Ces données ne sont ni vendues ni cédées. Vous pouvez demander leur consultation ou leur rectification au service client, et supprimer votre compte dans Compte › Supprimer mon compte."),
        Article("Responsabilité et litiges", "VIVI agit comme intermédiaire : le propriétaire reste responsable de son logement. En cas de difficulté, contactez d’abord le service client, qui recherche une solution amiable. Les présentes conditions sont soumises au droit béninois."),
    )
    val liste = rememberLazyListState()
    val portee = rememberCoroutineScope()
    val premierArticle = 2   // après l'introduction et le sommaire

    LazyColumn(state = liste, contentPadding = PaddingValues(start = 16.dp, end = 16.dp, bottom = 24.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Text("Conditions d’utilisation et de vente de la plateforme Appartement VIVI, éditée par Nova Tech Bénin.",
                style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)
        }
        item {
            Carte(remplissage = PaddingValues(vertical = 6.dp)) {
                Column {
                    Text("Sommaire", style = MaterialTheme.typography.titleSmall, modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp))
                    articles.forEachIndexed { i, a ->
                        Row(
                            Modifier.fillMaxWidth().heightIn(min = 44.dp)
                                .clickable(role = Role.Button) { portee.launch { liste.animateScrollToItem(premierArticle + i) } }
                                .padding(horizontal = 16.dp),
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text("${i + 1}.", color = Vivi.Teal700, fontWeight = FontWeight.Bold, modifier = Modifier.width(28.dp))
                            Text(a.titre, color = Vivi.Teal800, style = MaterialTheme.typography.bodyMedium)
                        }
                    }
                }
            }
        }
        articles.forEachIndexed { i, a ->
            item {
                Carte {
                    Column {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(Modifier.size(28.dp).clip(CircleShape).background(Vivi.Teal50), contentAlignment = Alignment.Center) {
                                Text("${i + 1}", color = Vivi.Teal800, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                            }
                            Spacer(Modifier.width(10.dp))
                            Text(a.titre, style = MaterialTheme.typography.titleMedium, modifier = Modifier.semantics { heading() })
                        }
                        Spacer(Modifier.height(8.dp))
                        Text(a.texte, style = MaterialTheme.typography.bodyMedium, color = Vivi.Body)
                        if (a.titre == "Annulation et report") {
                            Spacer(Modifier.height(8.dp))
                            BoutonContour("Voir les règles d’annulation", versAnnulation)
                        }
                    }
                }
            }
        }
        item { Text("Dernière mise à jour : 12 novembre 2026.", style = MaterialTheme.typography.bodySmall) }
        contact(c)
    }
}

/* ---------- Éléments communs ---------- */

@Composable
private fun Section(icone: ImageVector, titre: String, sousTitre: String? = null, contenu: @Composable () -> Unit) {
    Carte {
        Column {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(36.dp).clip(CircleShape).background(Vivi.Teal50), contentAlignment = Alignment.Center) {
                    Icon(icone, null, tint = Vivi.Teal700, modifier = Modifier.size(20.dp))
                }
                Spacer(Modifier.width(12.dp))
                Column {
                    Text(titre, style = MaterialTheme.typography.titleMedium, modifier = Modifier.semantics { heading() })
                    sousTitre?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                }
            }
            Spacer(Modifier.height(12.dp))
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) { contenu() }
        }
    }
}

/** Puce de liste : début en gras, suite normale. */
@Composable
private fun Puceur(fort: String, suite: String) {
    Row {
        Box(Modifier.padding(top = 8.dp).size(6.dp).clip(CircleShape).background(Vivi.Teal700))
        Spacer(Modifier.width(10.dp))
        Text(
            buildAnnotatedString {
                withStyle(SpanStyle(fontWeight = FontWeight.SemiBold, color = Vivi.Ink)) { append(fort) }
                append(suite)
            },
            style = MaterialTheme.typography.bodyMedium,
        )
    }
}

private fun LazyListScope.contact(c: Configuration) {
    val whatsapp = c.contact.whatsapp
    val email = c.contact.email
    if (whatsapp == null && email == null) return
    item {
        val context = LocalContext.current
        Carte {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("Une question ?", style = MaterialTheme.typography.titleMedium)
                Text("Le service client VIVI vous répond" + listOfNotNull(whatsapp?.let { " sur WhatsApp" }, email?.let { " par email" }).joinToString(" ou") + ".",
                    style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted)
                whatsapp?.let { BoutonContour("Nous écrire sur WhatsApp", { ouvrirLien(context, it) }, Modifier.fillMaxWidth(), icone = Icons.AutoMirrored.Outlined.Chat) }
                email?.let { BoutonContour(it, { ouvrirLien(context, "mailto:$it") }, Modifier.fillMaxWidth(), icone = Icons.Outlined.Email) }
            }
        }
    }
}
