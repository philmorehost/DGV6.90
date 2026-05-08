package com.dgv6.app.ui.dashboard

import android.os.Bundle
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.dgv6.app.R
import com.dgv6.app.api.RetrofitClient
import com.dgv6.app.util.PreferenceManager
import kotlinx.coroutines.launch

class AIChatActivity : AppCompatActivity() {
    private lateinit var tvLog: TextView
    private lateinit var etPrompt: EditText
    private lateinit var btnSend: Button
    private lateinit var prefs: PreferenceManager
    
    private val chatHistory = StringBuilder()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_ai_chat)
        
        prefs = PreferenceManager(this)
        tvLog = findViewById(R.id.tv_chat_log)
        etPrompt = findViewById(R.id.et_prompt)
        btnSend = findViewById(R.id.btn_send)
        
        btnSend.setOnClickListener {
            val prompt = etPrompt.text.toString().trim()
            if (prompt.isNotEmpty()) {
                appendLog("You: $prompt")
                etPrompt.setText("")
                sendToAi(prompt)
            }
        }
    }
    
    private fun appendLog(msg: String) {
        chatHistory.append(msg).append("\n\n")
        tvLog.text = chatHistory.toString()
    }
    
    private fun sendToAi(prompt: String) {
        btnSend.isEnabled = false
        btnSend.text = "Thinking..."
        
        lifecycleScope.launch {
            try {
                // We use RetrofitClient to make a custom call or we can just add an endpoint to VtuApiService
                // Since adding it to VtuApiService requires recompiling Retrofit interfaces across multiple apps,
                // we'll just do a raw OkHttp request to avoid breaking the Retrofit graph if it doesn't compile perfectly.
                val client = okhttp3.OkHttpClient()
                val json = """{"prompt": "", "page_context": "mobile_app"}"""
                val body = okhttp3.RequestBody.create(okhttp3.MediaType.parse("application/json"), json)
                
                val token = prefs.getToken() ?: ""
                val baseUrl = com.dgv6.app.util.Constants.BASE_URL
                val request = okhttp3.Request.Builder()
                    .url(baseUrl + "api/app-backend/ai-handler")
                    .addHeader("Authorization", "Bearer $token")
                    .post(body)
                    .build()
                    
                kotlinx.coroutines.Dispatchers.IO.invoke {
                    val response = client.newCall(request).execute()
                    val resStr = response.body()?.string()
                    kotlinx.coroutines.Dispatchers.Main.invoke {
                        if (response.isSuccessful && resStr != null) {
                            try {
                                val jsonObj = org.json.JSONObject(resStr)
                                if (jsonObj.optBoolean("success")) {
                                    appendLog("AI: " + jsonObj.optString("response"))
                                } else {
                                    appendLog("Error: " + jsonObj.optString("error", "Unknown error"))
                                }
                            } catch (e: Exception) {
                                appendLog("Error parsing response")
                            }
                        } else {
                            appendLog("Network Error: " + response.code())
                        }
                        btnSend.isEnabled = true
                        btnSend.text = "Send"
                    }
                }
            } catch (e: Exception) {
                appendLog("Error: " + e.message)
                btnSend.isEnabled = true
                btnSend.text = "Send"
            }
        }
    }
}
