package bj.vivi.app.ui.client

import android.content.Context
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Apartment
import androidx.compose.material.icons.outlined.Celebration
import androidx.compose.material.icons.outlined.EventRepeat
import androidx.compose.material.icons.outlined.PhoneAndroid
import androidx.compose.material.icons.outlined.QrCode2
import androidx.compose.material.icons.outlined.SearchOff
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.Logement
import bj.vivi.app.data.Position
import bj.vivi.app.data.Pub
import bj.vivi.app.data.Reseau
import bj.vivi.app.data.appel
import bj.vivi.app.ui.EcranViewModel
import bj.vivi.app.ui.Etat
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.composants.BoutonTexte
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.EspacePub
import bj.vivi.app.ui.composants.EtatErreur
import bj.vivi.app.ui.composants.EtatVide
import bj.vivi.app.ui.composants.SqueletteCarteLogement
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.rememberSuiviPub
import bj.vivi.app.ui.composants.salutationDuMoment
import bj.vivi.app.ui.theme.Outfit
import bj.vivi.app.ui.theme.Vivi
import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.withTimeoutOrNull

data class Selection(val logements: List<Logement>, val salles: List<Logement>, val presDeMoi: Boolean)

/** Accueil du visiteur et du client : logements et salles libres aujourd'hui, les plus proches d'abord si la position est connue. */
class AccueilViewModel : EcranViewModel<Selection>() {
    private var position: Position.Point? = null
    private var demarre = false

    suspend fun demarrer(context: Context) {
        if (demarre) return
        demarre = true
        // Pas de demande d'autorisation ici : elle est faite en ouvrant la recherche, quand elle est utile
        if (Position.autorisee(context)) position = withTimeoutOrNull(4_000) { Position.obtenir(context) }
        recharger()
    }

    override suspend fun charger(): Result<Selection> = coroutineScope {
        val logements = async { chercher("logement", 1) }
        val salles = async { chercher("salle", null) }
        val s = salles.await().getOrDefault(emptyList())
        logements.await().map { Selection(it, s, position != null) }
    }

    private suspend fun chercher(categorie: String, voyageurs: Int?): Result<List<Logement>> = appel {
        rechercher(
            ville = null, date = Fmt.aujourdhui().toString(), voyageurs = voyageurs, formule = null, categorie = categorie, budget = null,
            tri = if (position != null) "distance" else "pertinence", types = emptyList(), equipements = emptyList(),
            lat = position?.lat, lng = position?.lng, page = 1,
        )
    }.map { it.data.take(8) }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranAccueil(
    onLogements: () -> Unit,
    onSalles: () -> Unit,
    onLogement: (String) -> Unit,
    onNotifications: () -> Unit,
    nonLues: Int,
    onCompte: () -> Unit,
    vm: AccueilViewModel = viewModel(),
) {
    val utilisateur by Reseau.session.utilisateur.collectAsState()
    val context = LocalContext.current
    val pubs = rememberSuiviPub()
    LaunchedEffect(Unit) { vm.demarrer(context) }
    LaunchedEffect(utilisateur?.id) { Pub.charger(null) }

    PullToRefreshBox(isRefreshing = vm.actualisation, onRefresh = { vm.recharger(silencieux = true) }, modifier = Modifier.fillMaxSize()) {
        LazyColumn(Modifier.fillMaxSize().background(Vivi.Sand), contentPadding = PaddingValues(bottom = 24.dp)) {
            item {
                Column(Modifier.fillMaxWidth().background(Vivi.Teal900).statusBarsPadding().padding(horizontal = 16.dp).padding(top = 8.dp, bottom = 20.dp)) {
                    EnTeteMarque(onNotifications, nonLues, onCompte)
                    Spacer(Modifier.height(14.dp))
                    val prenom = utilisateur?.prenom?.takeIf { it.isNotBlank() }
                    val salut = salutationDuMoment()
                    Text(if (prenom != null) "$salut $prenom," else "$salut, bienvenue sur VIVI", color = Vivi.Teal200, fontSize = 15.sp, fontWeight = FontWeight.Medium)
                    Spacer(Modifier.height(4.dp))
                    Text("Que cherchez-vous ?", fontFamily = Outfit, fontWeight = FontWeight.Bold, fontSize = 24.sp, lineHeight = 29.sp, color = Vivi.White)
                    Spacer(Modifier.height(16.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        Entree("Un logement", "Appartements meublés", Icons.Outlined.Apartment, Vivi.Teal50, Vivi.Teal700, Modifier.weight(1f), onLogements)
                        Entree("Une salle", "Fêtes, réunions, conférences", Icons.Outlined.Celebration, Vivi.Orange50, Vivi.OrangeInk, Modifier.weight(1f), onSalles)
                    }
                }
            }
            item { EspacePub(Pub.ACCUEIL_HAUT, pubs, Modifier.padding(start = 16.dp, end = 16.dp, top = 16.dp)) }
            item {
                Row(Modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, top = 16.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Atout(Icons.Outlined.PhoneAndroid, "Mobile Money", Modifier.weight(1f))
                    Atout(Icons.Outlined.QrCode2, "Billet QR Code", Modifier.weight(1f))
                    Atout(Icons.Outlined.EventRepeat, "Report gratuit", Modifier.weight(1f))
                }
            }
            when (val e = vm.etat) {
                Etat.Chargement -> items(2) { SqueletteCarteLogement(Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) }
                is Etat.Erreur -> item { EtatErreur(e.erreur.message, e.erreur.horsLigne, onReessayer = { vm.recharger() }) }
                is Etat.Pret -> {
                    val s = e.donnees
                    if (s.logements.isEmpty() && s.salles.isEmpty()) item {
                        EtatVide(Icons.Outlined.SearchOff, "Rien de libre aujourd’hui", "Choisissez une autre date dans Logements ou Salles.")
                    }
                    rangee(if (s.presDeMoi) "Logements près de vous" else "Logements à découvrir", s.logements, onLogements, onLogement)
                    item { EspacePub(Pub.ACCUEIL_MILIEU, pubs, Modifier.padding(start = 16.dp, end = 16.dp, top = 20.dp)) }
                    rangee(if (s.presDeMoi) "Salles près de vous" else "Salles à découvrir", s.salles, onSalles, onLogement)
                }
            }
        }
    }
}

private fun LazyListScope.rangee(titre: String, liste: List<Logement>, onTout: () -> Unit, onLogement: (String) -> Unit) {
    if (liste.isEmpty()) return
    item {
        TitreSection(titre, Modifier.padding(start = 16.dp, end = 8.dp, top = 20.dp), surtitre = "Libres aujourd’hui") { BoutonTexte("Tout voir", onTout) }
        Spacer(Modifier.height(8.dp))
        LazyRow(contentPadding = PaddingValues(horizontal = 16.dp), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            items(liste, key = { it.id }) { l -> CarteProche(l) { onLogement(l.slug) } }
        }
    }
}

@Composable
private fun Entree(titre: String, texte: String, icone: ImageVector, fond: Color, teinte: Color, modifier: Modifier, onClick: () -> Unit) {
    Carte(modifier.heightIn(min = 124.dp), onClick = onClick, remplissage = PaddingValues(14.dp)) {
        Column {
            Box(Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).background(fond), contentAlignment = Alignment.Center) {
                Icon(icone, null, tint = teinte, modifier = Modifier.size(22.dp))
            }
            Spacer(Modifier.height(10.dp))
            Text(titre, style = MaterialTheme.typography.titleMedium)
            Text(texte, style = MaterialTheme.typography.bodySmall)
        }
    }
}
