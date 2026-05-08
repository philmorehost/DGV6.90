package com.dgv6.app.ui.dashboard

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Bundle
import android.speech.RecognitionListener
import android.speech.RecognizerIntent
import android.speech.SpeechRecognizer
import android.view.View
import android.widget.*
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import com.dgv6.app.R
import com.dgv6.app.api.RetrofitClient
import com.dgv6.app.util.Constants
import com.dgv6.app.util.PreferenceManager
import kotlinx.coroutines.launch
import org.json.JSONObject
import java.util.Locale

class AIChatActivity : AppCompatActivity() {
    private lateinit var tvLog: TextView
    private lateinit var etPrompt: EditText
    private lateinit var btnSend: Button
    private lateinit var btnMic: ImageButton
    private lateinit var progressBar: ProgressBar
    private lateinit var prefs: PreferenceManager
    
    private val chatHistory = StringBuilder()
    private var speechRecognizer: SpeechRecognizer? = null
    private val REQUEST_RECORD_AUDIO_PERMISSION = 200

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_ai_chat)
        
        prefs = PreferenceManager(this)
        tvLog = findViewById(R.id.tv_chat_log)
        etPrompt = findViewById(R.id.et_prompt)
        btnSend = findViewById(R.id.btn_send)
        btnMic = findViewById(R.id.btn_mic)
        
        // Add a progress bar to the layout programmatically if not present, 
        // but for now let's just use the Send button text
        
        appendLog("AI: Hello! I am your AI Assistant. You can type or use your voice to perform transactions. How can I help you today?")
        
        btnSend.setOnClickListener {
            val prompt = etPrompt.text.toString().trim()
            if (prompt.isNotEmpty()) {
                appendLog("You: $prompt")
                etPrompt.setText("")
                processIntent(prompt)
            }
        }
        
        setupSpeechRecognizer()
        checkAutonomousStatus()
    }

    private fun checkAutonomousStatus() {
        val voiceStatus = prefs.getAiVoiceStatus()
        if (voiceStatus != 2) {
            appendLog("System: Note - You are currently in 'Guided Mode'. You can ask questions, but autonomous transactions require approval. Your trust score is ${prefs.getTrustScore()}/100.")
        } else {
            appendLog("System: Autonomous Access Active. I can perform transactions directly based on your voice commands.")
        }
    }
    
    private fun setupSpeechRecognizer() {
        if (SpeechRecognizer.isRecognitionAvailable(this)) {
            speechRecognizer = SpeechRecognizer.createSpeechRecognizer(this)
            val intent = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
                putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM)
                putExtra(RecognizerIntent.EXTRA_LANGUAGE, Locale.getDefault())
            }

            speechRecognizer?.setRecognitionListener(object : RecognitionListener {
                override fun onReadyForSpeech(p0: Bundle?) { etPrompt.hint = "Listening..." }
                override fun onBeginningOfSpeech() {}
                override fun onRmsChanged(p0: Float) {}
                override fun onBufferReceived(p0: ByteArray?) {}
                override fun onEndOfSpeech() { 
                    etPrompt.hint = "Ask me anything..."
                    btnMic.alpha = 1.0f 
                }
                override fun onError(p0: Int) {
                    btnMic.alpha = 1.0f
                    Toast.makeText(this@AIChatActivity, "Speech error: $p0", Toast.LENGTH_SHORT).show()
                }
                override fun onResults(results: Bundle?) {
                    val data = results?.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION)
                    if (!data.isNullOrEmpty()) {
                        val spokenText = data[0]
                        etPrompt.setText(spokenText)
                        btnSend.performClick()
                    }
                }
                override fun onPartialResults(p0: Bundle?) {}
                override fun onEvent(p0: Int, p1: Bundle?) {}
            })

            btnMic.setOnClickListener {
                if (ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
                    ActivityCompat.requestPermissions(this, arrayOf(Manifest.permission.RECORD_AUDIO), REQUEST_RECORD_AUDIO_PERMISSION)
                } else {
                    btnMic.alpha = 0.5f
                    speechRecognizer?.startListening(intent)
                }
            }
        }
    }

    private fun processIntent(text: String) {
        setLoading(true)
        lifecycleScope.launch {
            try {
                val service = RetrofitClient.getService()
                val token = "Bearer " + prefs.getApiKey()
                val response = service.parseAiIntent(token, mapOf("voice_text" to text))
                
                if (response.isSuccessful && response.body() != null) {
                    val body = response.body()!!
                    val success = body["success"] as? Boolean ?: false
                    
                    if (success) {
                        val intent = body["intent"] as? Map<String, Any>
                        val needsConfirm = body["needs_confirmation"] as? Boolean ?: true
                        
                        if (intent != null && intent["service"] != null) {
                            handleVtuIntent(intent, needsConfirm)
                        } else {
                            val aiResponse = body["response"] as? String ?: "I'm not sure how to help with that yet."
                            appendLog("AI: $aiResponse")
                        }
                    } else {
                        appendLog("Error: " + (body["error"] ?: "Unknown error"))
                    }
                } else {
                    appendLog("Error: Server returned " + response.code())
                }
            } catch (e: Exception) {
                appendLog("Error: " + e.localizedMessage)
            } finally {
                setLoading(false)
            }
        }
    }

    private fun handleVtuIntent(intent: Map<String, Any>, needsConfirm: Boolean) {
        val service = intent["service"] as String
        val amount = intent["amount"]?.toString() ?: "0"
        val phone = intent["phone"]?.toString() ?: ""
        val network = intent["network"]?.toString() ?: ""
        
        val summary = "Purchase $network $service of ₦$amount for $phone"
        
        if (needsConfirm) {
            AlertDialog.Builder(this)
                .setTitle("Confirm Transaction")
                .setMessage("AI has detected this intent:\n\n$summary\n\nProceed?")
                .setPositiveButton("Yes") { _, _ -> executeTransaction(intent) }
                .setNegativeButton("No", null)
                .show()
        } else {
            appendLog("AI: Executing $summary...")
            executeTransaction(intent)
        }
    }

    private fun executeTransaction(intent: Map<String, Any>) {
        setLoading(true)
        lifecycleScope.launch {
            try {
                val api = RetrofitClient.getService()
                val serviceType = intent["service"] as String
                val params = mutableMapOf<String, Any>(
                    "api_key" to prefs.getApiKey(),
                    "amount" to (intent["amount"] ?: 0),
                    "phone" to (intent["phone"] ?: ""),
                    "network" to (intent["network"] ?: "")
                )
                
                // Add specific fields
                intent["plan_type"]?.let { params["plan_type"] = it }
                intent["smartcard"]?.let { params["smartcard"] = it }
                
                val response = when(serviceType) {
                    "airtime" -> api.purchaseAirtime(params)
                    "data" -> api.purchaseData(params)
                    "cable" -> api.purchaseCable(params)
                    "electric" -> api.purchaseElectric(params)
                    else -> null
                }
                
                if (response?.isSuccessful == true) {
                    val resBody = response.body()
                    val msg = resBody?.get("desc")?.toString() ?: "Transaction processed"
                    appendLog("AI Result: $msg")
                } else {
                    appendLog("Execution Error: " + (response?.code() ?: "Unsupported service"))
                }
            } catch (e: Exception) {
                appendLog("Execution Failed: " + e.localizedMessage)
            } finally {
                setLoading(false)
            }
        }
    }

    private fun setLoading(loading: Boolean) {
        btnSend.isEnabled = !loading
        btnSend.text = if (loading) "..." else "Send"
    }

    private fun appendLog(msg: String) {
        chatHistory.append(msg).append("\n\n")
        tvLog.text = chatHistory.toString()
        // Scroll to bottom
        findViewById<ScrollView>(R.id.sv_chat).post {
            findViewById<ScrollView>(R.id.sv_chat).fullScroll(View.FOCUS_DOWN)
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        speechRecognizer?.destroy()
    }
}
