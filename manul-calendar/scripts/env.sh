# Окружение для сборки: JDK 17 и Android SDK, установленные в домашнюю папку (без sudo).
# Использование:  source scripts/env.sh && ./gradlew assembleDebug
export JAVA_HOME="$HOME/.local/jdk-17"
export ANDROID_HOME="$HOME/Android/Sdk"
export ANDROID_SDK_ROOT="$ANDROID_HOME"
export PATH="$JAVA_HOME/bin:$ANDROID_HOME/platform-tools:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"
