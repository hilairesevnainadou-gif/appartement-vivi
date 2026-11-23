package bj.vivi.app

import android.app.Application
import android.content.Intent
import android.graphics.Color
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.SystemBarStyle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import bj.vivi.app.data.Reseau
import bj.vivi.app.ui.AppVivi
import bj.vivi.app.ui.LiensEntrants
import bj.vivi.app.ui.theme.ViviTheme

class ViviApplication : Application(), coil.ImageLoaderFactory {
    override fun newImageLoader() = Reseau.chargeurImages(this)

    override fun onCreate() {
        super.onCreate()
        Reseau.initialiser(this)   // la session chiffrée s'ouvre en arrière-plan
        bj.vivi.app.ui.CodesAgent.initialiser(this)
        // Hors du fil principal : rien de tout ça n'est nécessaire pour dessiner le premier écran
        Thread({
            bj.vivi.app.ui.Verrou.initialiser(this)          // attend la session (jeton)
            // Notifications du téléphone : canal + vérification en arrière-plan toutes les 15 min
            bj.vivi.app.data.Alertes.creerCanal(this)
            bj.vivi.app.data.Alertes.planifier(this)
        }, "vivi-demarrage").start()
        // Verrou après 1 min en arrière-plan (tout le processus, pas seulement l'activité)
        androidx.lifecycle.ProcessLifecycleOwner.get().lifecycle.addObserver(object : androidx.lifecycle.DefaultLifecycleObserver {
            override fun onStop(owner: androidx.lifecycle.LifecycleOwner) = bj.vivi.app.ui.Verrou.surArrierePlan()
            override fun onStart(owner: androidx.lifecycle.LifecycleOwner) = bj.vivi.app.ui.Verrou.surPremierPlan()
        })
    }
}

/** FragmentActivity (et non ComponentActivity) : nécessaire à la fenêtre d'empreinte (BiometricPrompt). */
class MainActivity : androidx.fragment.app.FragmentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        // Bord à bord, icônes système sombres sur fond clair (en-têtes sombres : contenu sous la barre d'état)
        enableEdgeToEdge(
            statusBarStyle = SystemBarStyle.auto(Color.TRANSPARENT, Color.TRANSPARENT),
            navigationBarStyle = SystemBarStyle.light(Color.WHITE, Color.WHITE),
        )
        super.onCreate(savedInstanceState)
        LiensEntrants.recevoir(intent?.data)
        LiensEntrants.recevoirNotification(intent)
        setContent { ViviTheme { AppVivi() } }
    }

    /** Retour de la page de paiement : vivi://paiement/{id}?statut=… (launchMode singleTask). */
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        LiensEntrants.recevoir(intent.data)
        LiensEntrants.recevoirNotification(intent)
    }
}
