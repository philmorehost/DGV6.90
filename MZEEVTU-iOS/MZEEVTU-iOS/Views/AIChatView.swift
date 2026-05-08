import SwiftUI

struct AIChatView: View {
    @State private var prompt: String = ""
    @State private var chatHistory: [ChatMessage] = []
    @State private var isLoading: Bool = false
    @Environment(\.presentationMode) var presentationMode
    
    struct ChatMessage: Identifiable {
        let id = UUID()
        let isUser: Bool
        let text: String
    }
    
    var body: some View {
        NavigationView {
            VStack {
                ScrollViewReader { proxy in
                    ScrollView {
                        VStack(spacing: 12) {
                            ForEach(chatHistory) { message in
                                HStack {
                                    if message.isUser { Spacer() }
                                    Text(message.text)
                                        .padding(12)
                                        .background(message.isUser ? Color.blue : Color(UIColor.secondarySystemBackground))
                                        .foregroundColor(message.isUser ? .white : .primary)
                                        .cornerRadius(16)
                                    if !message.isUser { Spacer() }
                                }
                                .padding(.horizontal)
                                .id(message.id)
                            }
                        }
                        .padding(.vertical)
                    }
                    .onChange(of: chatHistory.count) { _ in
                        withAnimation {
                            if let last = chatHistory.last {
                                proxy.scrollTo(last.id, anchor: .bottom)
                            }
                        }
                    }
                }
                
                HStack {
                    TextField("Ask me anything...", text: $prompt)
                        .padding(12)
                        .background(Color(UIColor.systemGray6))
                        .cornerRadius(20)
                        .disabled(isLoading)
                    
                    Button(action: sendMessage) {
                        if isLoading {
                            ProgressView()
                                .padding()
                        } else {
                            Image(systemName: "paperplane.fill")
                                .foregroundColor(.white)
                                .padding()
                                .background(prompt.isEmpty ? Color.gray : Color.blue)
                                .clipShape(Circle())
                        }
                    }
                    .disabled(prompt.isEmpty || isLoading)
                }
                .padding()
            }
            .navigationTitle("AI Assistant")
            .navigationBarTitleDisplayMode(.inline)
            .navigationBarItems(trailing: Button("Close") {
                presentationMode.wrappedValue.dismiss()
            })
        }
    }
    
    func sendMessage() {
        let userMsg = prompt.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !userMsg.isEmpty else { return }
        
        chatHistory.append(ChatMessage(isUser: true, text: userMsg))
        prompt = ""
        isLoading = true
        
        let params: [String: Any] = ["prompt": userMsg, "page_context": "ios_app"]
        
        // Use AppNetworkService to hit the ai-handler endpoint
        AppNetworkService.shared.request("app-backend/ai-handler", params: params) { (result: Result<APIResponse, Error>) in
            DispatchQueue.main.async {
                self.isLoading = false
                switch result {
                case .success(let response):
                    if response.success == true, let aiResponse = response.response {
                        self.chatHistory.append(ChatMessage(isUser: false, text: aiResponse))
                    } else {
                        self.chatHistory.append(ChatMessage(isUser: false, text: "Error: \(response.error ?? "Unknown error")"))
                    }
                case .failure(let error):
                    self.chatHistory.append(ChatMessage(isUser: false, text: "Network Error: \(error.localizedDescription)"))
                }
            }
        }
    }
}
