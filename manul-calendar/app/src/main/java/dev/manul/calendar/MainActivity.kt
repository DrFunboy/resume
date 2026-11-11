package dev.manul.calendar

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import dev.manul.calendar.ui.home.HomeScreen
import dev.manul.calendar.ui.theme.ManulCalendarTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        // Системный сплэш Android 12+. Анимация «зевок» будет отдельным Compose-экраном поверх него (ROADMAP, шаг 12).
        installSplashScreen()
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            ManulCalendarTheme {
                HomeScreen()
            }
        }
    }
}
