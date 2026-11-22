@file:OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)

package bj.vivi.app.ui.compte

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.AccountBalance
import androidx.compose.material.icons.outlined.Check
import androidx.compose.material.icons.outlined.Fingerprint
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.PhoneAndroid
import androidx.compose.material.icons.outlined.VerifiedUser
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import bj.vivi.app.data.CompteRetrait
import bj.vivi.app.data.appel
import bj.vivi.app.data.corps
import bj.vivi.app.data.numeroLisible
import bj.vivi.app.ui.AvecEtat
import bj.vivi.app.ui.EcranViewModel
import bj.vivi.app.ui.Fmt
import bj.vivi.app.ui.Messages
import bj.vivi.app.ui.composants.Bandeau
import bj.vivi.app.ui.composants.BarreAction
import bj.vivi.app.ui.composants.BarreTitre
import bj.vivi.app.ui.composants.BoutonPrincipal
import bj.vivi.app.ui.composants.Carte
import bj.vivi.app.ui.composants.ChampTexte
import bj.vivi.app.ui.composants.SqueletteDetail
import bj.vivi.app.ui.composants.TitreSection
import bj.vivi.app.ui.composants.TonPuce
import bj.vivi.app.ui.confirmerIdentite
import bj.vivi.app.ui.theme.Vivi

/**
 * Compte de retrait : moyen (MTN, Moov, Celtiis, virement), numéro et titulaire.
 * Chaque demande de retrait recopie le compte en vigueur.
 */
class CompteRetraitViewModel : EcranViewModel<CompteRetrait>() {
    var moyen by mutableStateOf<String?>(null)
    var numero by mutableStateOf("")
    var titulaire by mutableStateOf("")
    var erreurs by mutableStateOf<Map<String, String>>(emptyMap())
        private set

    init { recharger() }

    override suspend fun charger() = appel { compteRetrait() }.map { it.data }.onSuccess { c ->
        moyen = c.moyen ?: c.moyens.firstOrNull()
        numero = c.numero?.let(::numeroLisible).orEmpty()
        titulaire = c.titulaire ?: bj.vivi.app.data.Reseau.session.utilisateur.value?.nom.orEmpty()
    }

    fun effacer(champ: String) { if (champ in erreurs) erreurs = erreurs - champ }

    fun enregistrer(fait: () -> Unit) {
        erreurs = emptyMap()
        action(surErreur = { e -> erreurs = e.champs.ifEmpty { mapOf("numero" to e.message) } }) {
            appel { enregistrerCompteRetrait(corps("moyen" to moyen, "numero" to numero.trim(), "titulaire" to titulaire.trim())) }.map { r ->
                remplacer(r.data)
                Messages.afficher(r.message ?: "Compte de retrait enregistré.")
                fait()
            }
        }
    }
}

private fun estVirement(moyen: String?) = moyen?.lowercase()?.contains("virement") == true

@Composable
fun EcranCompteRetrait(onRetour: () -> Unit, vm: CompteRetraitViewModel = viewModel()) {
    val context = LocalContext.current
    Scaffold(
        containerColor = Vivi.Sand,
        topBar = { BarreTitre("Compte de retrait", onRetour) },
        bottomBar = {
            if (vm.etat is bj.vivi.app.ui.Etat.Pret) BarreAction(Modifier.navigationBarsPadding().imePadding()) {
                BoutonPrincipal(
                    "Enregistrer", { vm.enregistrer(onRetour) },
                    Modifier.weight(1f), actif = vm.moyen != null && vm.numero.isNotBlank() && vm.titulaire.trim().length >= 3,
                    enCours = vm.enCours, icone = Icons.Outlined.Check,
                )
            }
        },
    ) { marges ->
        AvecEtat(vm, Modifier.padding(marges), actualisable = false, squelette = { SqueletteDetail(it) }) { c ->
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                // Compte actuel
                Carte(fond = if (c.configure) Vivi.Teal900 else Vivi.Orange50) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(Modifier.size(44.dp).clip(CircleShape).background(if (c.configure) Vivi.White.copy(alpha = .12f) else Vivi.White), contentAlignment = Alignment.Center) {
                            Icon(if (c.configure) Icons.Outlined.VerifiedUser else Icons.Outlined.Info, null, tint = if (c.configure) Vivi.Orange400 else Vivi.OrangeInk)
                        }
                        Spacer(Modifier.width(12.dp))
                        Column(Modifier.weight(1f)) {
                            if (c.configure) {
                                Text("Vos retraits sont payés sur", style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Teal200))
                                Text(c.resume, style = MaterialTheme.typography.titleMedium.copy(color = Vivi.White))
                                Text(c.titulaire.orEmpty() + (c.modifieLe?.let { " · modifié le ${Fmt.numerique(it)}" } ?: ""), style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Teal100))
                            } else {
                                Text("Aucun compte de retrait", style = MaterialTheme.typography.titleMedium, color = Vivi.OrangeInk)
                                Text("Indiquez où recevoir votre argent avant votre premier retrait.", style = MaterialTheme.typography.bodySmall)
                            }
                        }
                    }
                }

                Carte {
                    Column(verticalArrangement = Arrangement.spacedBy(14.dp)) {
                        TitreSection(if (c.configure) "Changer de compte" else "Mon compte de retrait")
                        Text("Moyen de retrait", style = MaterialTheme.typography.labelMedium, color = Vivi.Ink)
                        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            c.moyens.forEach { m ->
                                val choisi = m == vm.moyen
                                FilterChip(
                                    selected = choisi, onClick = { vm.moyen = m; vm.effacer("moyen") },
                                    label = { Text(m, fontWeight = FontWeight.SemiBold, color = if (choisi) Vivi.White else Vivi.Body) },
                                    leadingIcon = { Icon(if (estVirement(m)) Icons.Outlined.AccountBalance else Icons.Outlined.PhoneAndroid, null, Modifier.size(18.dp), tint = if (choisi) Vivi.White else Vivi.Teal700) },
                                    shape = RoundedCornerShape(999.dp), modifier = Modifier.heightIn(min = 44.dp),
                                    colors = FilterChipDefaults.filterChipColors(selectedContainerColor = Vivi.Teal700, containerColor = Vivi.White),
                                    border = FilterChipDefaults.filterChipBorder(enabled = true, selected = choisi, borderColor = Vivi.LineStrong, selectedBorderColor = Vivi.Teal700),
                                )
                            }
                        }
                        vm.erreurs["moyen"]?.let { Text(it, style = MaterialTheme.typography.bodySmall.copy(color = Vivi.Danger)) }
                        if (estVirement(vm.moyen)) {
                            ChampTexte(vm.numero, { vm.numero = it.uppercase().take(40); vm.effacer("numero") }, "RIB ou IBAN", indication = "BJ00 0000 0000 0000 …", erreur = vm.erreurs["numero"])
                        } else {
                            ChampTexte(vm.numero, { vm.numero = it.filter { ch -> ch.isDigit() || ch == ' ' || ch == '+' }.take(16); vm.effacer("numero") }, "Numéro Mobile Money",
                                indication = "01 97 00 00 00", clavier = KeyboardType.Phone, aide = "Le numéro doit être enregistré chez ${vm.moyen ?: "l’opérateur"}.", erreur = vm.erreurs["numero"])
                        }
                        ChampTexte(vm.titulaire, { vm.titulaire = it.take(120); vm.effacer("titulaire") }, "Nom du titulaire du compte",
                            aide = "Tel qu’il apparaît chez l’opérateur ou à la banque.", erreur = vm.erreurs["titulaire"])
                    }
                }
                Bandeau(
                    "Chaque demande de retrait garde le compte en vigueur au moment de la demande : la caissière VIVI paie sur ce numéro.",
                    ton = TonPuce.Teal, icone = Icons.Outlined.Info,
                )
            }
        }
    }
}

/** Ligne « Payé sur … · Modifier » réutilisée par les écrans de revenus (propriétaire, agent). */
@Composable
fun LigneCompteRetrait(compte: CompteRetrait?, onModifier: () -> Unit, clair: Boolean = false) {
    Row(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(if (clair) Vivi.White.copy(alpha = .1f) else Vivi.Teal50)
            .padding(horizontal = 12.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(if (compte?.configure == true) Icons.Outlined.Check else Icons.Outlined.Info, null,
            tint = if (clair) Vivi.Orange400 else Vivi.Teal700, modifier = Modifier.size(18.dp))
        Spacer(Modifier.width(8.dp))
        Text(
            if (compte?.configure == true) "Payé sur ${compte.resume}" else "Aucun compte de retrait",
            style = MaterialTheme.typography.bodySmall.copy(color = if (clair) Vivi.White else Vivi.Ink), modifier = Modifier.weight(1f), maxLines = 2,
        )
        bj.vivi.app.ui.composants.BoutonTexte(if (compte?.configure == true) "Modifier" else "Configurer", onModifier, couleur = if (clair) Vivi.Orange400 else Vivi.Teal700)
    }
}
