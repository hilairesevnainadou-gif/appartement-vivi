package bj.vivi.app.ui.pro

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AccountBalanceWallet
import androidx.compose.material.icons.outlined.EventAvailable
import androidx.compose.material.icons.outlined.Notifications
import androidx.compose.material.icons.outlined.QrCodeScanner
import androidx.compose.material.icons.outlined.WorkspacePremium
import androidx.compose.material3.Badge
import androidx.compose.material3.BadgedBox
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.Reservation
import bj.vivi.app.data.Reseau
import bj.vivi.app.data.TableauDeBord
import bj.vivi.app.data.appel
import bj.vivi.app.ui.AvecEtat
import bj.vivi.app.ui.EcranViewModel
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.composants.Avatar
import bj.vivi.app.ui.composants.Bandeau
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.BoutonTexte
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.EtatVide
import bj.vivi.app.ui.composants.Marque
import bj.vivi.app.ui.composants.PuceStatut
import bj.vivi.app.ui.composants.Separateur
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.pluriel
import bj.vivi.app.ui.theme.Outfit
import bj.vivi.app.ui.theme.Vivi
import java.time.LocalDate
import java.time.format.TextStyle

class TableauDeBordViewModel : EcranViewModel<TableauDeBord>() {
    init { recharger() }
    override suspend fun charger() = appel { tableauDeBord() }.map { it.data }
}

@Composable
fun EcranTableauDeBord(
    onScanner: () -> Unit,
    onReservation: (String) -> Unit,
    onRevenus: () -> Unit,
    onAbonnement: () -> Unit,
    onNotifications: () -> Unit,
    nonLues: Int,
    onCompte: () -> Unit = {},
    vm: TableauDeBordViewModel = viewModel(),
) {
    val u by Reseau.session.utilisateur.collectAsState()
    Column(Modifier.fillMaxSize().background(Vivi.Sand)) {
        // En-tête sombre (menu latéral du site : turquoise foncé)
        Column(Modifier.fillMaxWidth().background(Vivi.Teal900).statusBarsPadding().padding(horizontal = 16.dp).padding(top = 8.dp, bottom = 16.dp)) {
            // Logo à gauche, face à l'avatar du profil à droite
            Row(verticalAlignment = Alignment.CenterVertically) {
                Marque(blanc = true, taille = 28.dp)
                Spacer(Modifier.weight(1f))
                IconButton(onClick = onNotifications) {
                    BadgedBox(badge = { if (nonLues > 0) Badge(containerColor = Vivi.Orange400, contentColor = Vivi.Ink) { Text("$nonLues") } }) {
                        Icon(Icons.Outlined.Notifications, contentDescription = "Notifications" + if (nonLues > 0) ", $nonLues non lues" else "", tint = Vivi.White)
                    }
                }
                bj.vivi.app.ui.composants.AvatarProfil(u != null, u?.nom, onCompte)
            }
            Spacer(Modifier.height(12.dp))
            Text(bj.vivi.app.ui.composants.salutationDuMoment() + " " + u?.prenom.orEmpty(), fontFamily = Outfit, fontWeight = FontWeight.SemiBold, fontSize = 22.sp, color = Vivi.White)
            Text(Fmt.jourLong(Fmt.aujourdhui()) + " · " + (u?.libelleRole ?: ""), color = Vivi.Teal200, fontSize = 14.sp)
        }
        AvecEtat(vm, squelette = { bj.vivi.app.ui.composants.SqueletteTableauDeBord(it) }) { t -> Contenu(t, onScanner, onReservation, onRevenus, onAbonnement) }
    }
}

@Composable
private fun Contenu(t: TableauDeBord, onScanner: () -> Unit, onReservation: (String) -> Unit, onRevenus: () -> Unit, onAbonnement: () -> Unit) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        if (t.estProprietaire && t.abonnement == null) {
            Carte(onClick = onAbonnement, fond = Vivi.Orange50) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Outlined.WorkspacePremium, null, tint = Vivi.OrangeInk)
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f)) {
                        Text("Aucun abonnement actif", style = MaterialTheme.typography.titleSmall, color = Vivi.OrangeInk)
                        Text("Un abonnement est nécessaire pour publier vos logements.", style = MaterialTheme.typography.bodySmall.copy(color = Vivi.OrangeInk))
                    }
                }
            }
        }

        // ---------- Indicateurs (.kpi) ----------
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Kpi("Revenus du mois", Fmt.fcfa(t.revenus.mois), variation(t.revenus.mois, t.revenus.moisPrecedent), Modifier.weight(1f))
            Kpi("Occupation", "${t.occupation.mois} %", "${t.occupation.mois - t.occupation.moisPrecedent} pts" to (t.occupation.mois >= t.occupation.moisPrecedent), Modifier.weight(1f))
        }
        if (t.estProprietaire && t.solde != null) {
            Carte(onClick = onRevenus) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(44.dp).clip(RoundedCornerShape(12.dp)).background(Vivi.Teal50), contentAlignment = Alignment.Center) {
                        Icon(Icons.Outlined.AccountBalanceWallet, null, tint = Vivi.Teal700)
                    }
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f)) {
                        Text("Solde disponible", style = MaterialTheme.typography.bodySmall)
                        Text(Fmt.fcfa(t.solde), style = MaterialTheme.typography.headlineSmall)
                    }
                    BoutonTexte("Retirer", onRevenus)
                }
            }
        }

        // ---------- Arrivées du jour ----------
        Carte {
            Column {
                TitreSection("Arrivées du jour", action = { if (t.arriveesDuJour.isNotEmpty()) BoutonTexte("Scanner", onScanner, icone = Icons.Outlined.QrCodeScanner) })
                if (t.arriveesDuJour.isEmpty()) {
                    Text("Aucune arrivée prévue aujourd’hui.", style = MaterialTheme.typography.bodyMedium, color = Vivi.Muted, modifier = Modifier.padding(vertical = 12.dp))
                } else {
                    t.arriveesDuJour.forEach { r -> Separateur(); LigneSejour(r) { onReservation(r.reference) } }
                }
            }
        }
        BoutonPrincipal("Scanner un QR Code", onScanner, Modifier.fillMaxWidth(), icone = Icons.Outlined.QrCodeScanner)

        // ---------- Revenus des 7 derniers jours ----------
        Carte {
            Column {
                TitreSection("Revenus des 7 derniers jours")
                Spacer(Modifier.height(12.dp))
                Graphique(t.revenus.septJours)
            }
        }

        // ---------- Prochains séjours ----------
        Carte {
            Column {
                TitreSection("Les 7 prochains jours", surtitre = pluriel(t.prochaines.size, "séjour"))
                if (t.prochaines.isEmpty()) {
                    EtatVide(Icons.Outlined.EventAvailable, "Rien de prévu pour l’instant")
                } else {
                    t.prochaines.forEach { r -> Separateur(); LigneSejour(r, avecJour = true) { onReservation(r.reference) } }
                }
            }
        }
        t.abonnement?.let { a ->
            Bandeau(
                "Formule ${a.formule}${if (a.essai) " (essai)" else ""} jusqu’au ${Fmt.jourLong(a.fin)} · ${pluriel(t.logementsPublies, "logement")} publié" + if (t.logementsPublies > 1) "s" else "",
                ton = TonPuce.Teal, icone = Icons.Outlined.WorkspacePremium,
            )
        }
        Spacer(Modifier.height(8.dp))
    }
}

private fun variation(actuel: Int, precedent: Int): Pair<String, Boolean>? {
    if (precedent <= 0) return null
    val pct = ((actuel - precedent) * 100.0 / precedent).toInt()
    return "${if (pct >= 0) "+" else ""}$pct % vs mois dernier" to (pct >= 0)
}

@Composable
private fun Kpi(libelle: String, valeur: String, delta: Pair<String, Boolean>?, modifier: Modifier) {
    Carte(modifier) {
        Column {
            Text(libelle, style = MaterialTheme.typography.bodySmall)
            Spacer(Modifier.height(4.dp))
            Text(valeur, fontFamily = Outfit, fontWeight = FontWeight.SemiBold, fontSize = 22.sp, color = Vivi.Ink, maxLines = 1)
            delta?.let { (texte, hausse) ->
                Spacer(Modifier.height(4.dp))
                Text((if (hausse) "▲ " else "▼ ") + texte, fontSize = 12.sp, color = if (hausse) Vivi.Success else Vivi.Danger, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

/** Ligne d'un séjour (.rows) : heure, client, logement, statut. */
@Composable
fun LigneSejour(r: Reservation, avecJour: Boolean = false, onClick: () -> Unit) {
    Row(Modifier.fillMaxWidth().padding(vertical = 12.dp), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.widthIn(min = 58.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Text(Fmt.heure(r.debut), fontFamily = Outfit, fontWeight = FontWeight.SemiBold, color = Vivi.Ink, fontSize = 16.sp)
            Text(if (avecJour) Fmt.jourCourt(r.debut) else r.duree, fontSize = 12.sp, color = Vivi.Muted, textAlign = TextAlign.Center)
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(r.client?.nom ?: "Client", style = MaterialTheme.typography.titleSmall)
            Text(r.avecPersonnes(r.logement?.titre.orEmpty()), style = MaterialTheme.typography.bodySmall, maxLines = 1)
        }
        Spacer(Modifier.width(8.dp))
        Column(horizontalAlignment = Alignment.End) {
            PuceStatut(r.statut)
            if (r.estManuelle && r.resteAPayer > 0) Text("Reste ${Fmt.fcfa(r.resteAPayer)}", fontSize = 12.sp, color = Vivi.OrangeInk)
        }
        BoutonTexte("Voir", onClick)
    }
}

/** Graphique en colonnes à une seule série (.chart), avec le montant au-dessus de chaque barre. */
@Composable
private fun Graphique(jours: List<TableauDeBord.Jour>) {
    val max = (jours.maxOfOrNull { it.montant } ?: 0).coerceAtLeast(1)
    val resume = jours.joinToString(", ") { "${LocalDate.parse(it.jour).dayOfWeek.getDisplayName(TextStyle.SHORT, Fmt.locale)} ${Fmt.fcfa(it.montant)}" }
    Row(
        Modifier.fillMaxWidth().height(170.dp).semantics { contentDescription = "Revenus par jour : $resume" },
        horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.Bottom,
    ) {
        jours.forEach { j ->
            val d = LocalDate.parse(j.jour)
            val aujourdhui = d == Fmt.aujourdhui()
            Column(Modifier.weight(1f).fillMaxHeight(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Bottom) {
                if (j.montant > 0) Text(abrege(j.montant), fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Vivi.Ink, maxLines = 1)
                Spacer(Modifier.height(4.dp))
                Box(
                    Modifier.fillMaxWidth(.6f).fillMaxHeight((j.montant.toFloat() / max * .78f).coerceAtLeast(.02f))
                        .clip(RoundedCornerShape(topStart = 4.dp, topEnd = 4.dp)).background(if (j.montant > 0) Vivi.Chart else Vivi.Line),
                )
                Spacer(Modifier.height(6.dp))
                Text(
                    d.dayOfWeek.getDisplayName(TextStyle.SHORT, Fmt.locale).replaceFirstChar { it.titlecase(Fmt.locale) },
                    fontSize = 12.sp, color = if (aujourdhui) Vivi.Ink else Vivi.Muted, fontWeight = if (aujourdhui) FontWeight.Bold else FontWeight.Normal,
                )
            }
        }
    }
}

private fun abrege(m: Int) = when {
    m >= 1_000_000 -> "%.1f M".format(Fmt.locale, m / 1_000_000.0)
    m >= 1000 -> "${m / 1000} k"
    else -> "$m"
}

