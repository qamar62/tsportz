import { MessageCircle } from "lucide-react";

export function FloatingChat() {
  return <a className="floating-chat" href="https://wa.me/923353631555?text=Hello%20TABEER%20SPORTZ%2C%20I%27d%20like%20to%20ask%20about%20your%20products." target="_blank" rel="noopener noreferrer" aria-label="Chat with TABEER SPORTZ on WhatsApp"><span className="chat-label">CHAT WITH US</span><span className="chat-icon"><MessageCircle size={25} strokeWidth={2} /><i aria-hidden="true" /></span></a>;
}
