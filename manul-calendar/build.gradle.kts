// Плагины объявлены здесь без применения, чтобы версии жили в одном месте (gradle/libs.versions.toml).
// AGP 9.x содержит встроенную поддержку Kotlin, поэтому плагин org.jetbrains.kotlin.android не нужен.
plugins {
    alias(libs.plugins.android.application) apply false
    alias(libs.plugins.kotlin.compose) apply false
}
