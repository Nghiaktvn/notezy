import React, { useState, useEffect, useRef } from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import AiAssistant from './components/AiAssistant.jsx';
import { ThemeProvider } from './context/ThemeContext.jsx';
import Landing from './pages/Landing.jsx';
import Login from './pages/Login.jsx';
import Register from './pages/Register.jsx';

const Dashboard = () => {
  const [isRecording, setIsRecording] = useState(false);
  const [noteContent, setNoteContent] = useState('');
  const [currentNoteId] = useState(0);
  const recognitionRef = useRef(null);

  useEffect(() => {
    if ('webkitSpeechRecognition' in window) {
      const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
      recognitionRef.current = new SpeechRecognition();
      recognitionRef.current.continuous = true;
      recognitionRef.current.interimResults = true;
      recognitionRef.current.lang = 'vi-VN';

      recognitionRef.current.onresult = (event) => {
        let finalTranscript = '';
        for (let i = event.resultIndex; i < event.results.length; ++i) {
          if (event.results[i].isFinal) {
            finalTranscript += event.results[i][0].transcript;
          }
        }
        if (finalTranscript) {
          setNoteContent(prev => prev + ' ' + finalTranscript);
        }
      };

      recognitionRef.current.onerror = (event) => {
        console.error("Speech recognition error", event.error);
        setIsRecording(false);
      };
    }
  }, []);

  const toggleRecording = () => {
    if (isRecording) {
      recognitionRef.current?.stop();
    } else {
      recognitionRef.current?.start();
    }
    setIsRecording(!isRecording);
  };

  return (
    <div className="min-h-screen bg-gray-50 flex">
      <div className="w-64 bg-white shadow-md p-4 flex flex-col">
        <h1 className="text-2xl font-bold mb-8">Notezy AI</h1>
        <ul className="space-y-2 flex-1">
          <li className="font-medium p-2 hover:bg-gray-100 rounded-md cursor-pointer">📝 Ghi chú & Wiki</li>
          <li className="font-medium p-2 bg-gray-100 rounded-md cursor-pointer">✅ Kanban Board</li>
          <li className="font-medium p-2 hover:bg-gray-100 rounded-md cursor-pointer">📅 Lịch (Calendar)</li>
        </ul>
      </div>

      <div className="flex-1 p-8 relative">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-3xl font-semibold">Bảng Công Việc (Kanban)</h2>
        </div>

        <div className="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-8">
          <h3 className="font-semibold mb-2 flex items-center gap-2">
            🎙️ Tạo ghi chú nhanh bằng giọng nói
          </h3>
          <textarea
            className="w-full border border-gray-300 rounded p-3 min-h-[100px] focus:outline-none focus:ring-2 focus:ring-black"
            placeholder="Nhấn vào biểu tượng micro và bắt đầu nói..."
            value={noteContent}
            onChange={(e) => setNoteContent(e.target.value)}
          ></textarea>
          <div className="mt-3 flex justify-between items-center">
            <button
              onClick={toggleRecording}
              className={`px-4 py-2 rounded text-white font-medium flex items-center gap-2 ${isRecording ? 'bg-red-500 animate-pulse' : 'bg-black hover:bg-gray-800'}`}
            >
              {isRecording ? '🛑 Đang ghi âm (Dừng)' : '🎤 Bắt đầu ghi âm'}
            </button>
            <button className="px-4 py-2 border border-black rounded font-medium hover:bg-gray-100">
              Lưu vào Kanban
            </button>
          </div>
        </div>

        <div className="grid grid-cols-3 gap-6">
          <div className="bg-gray-200 p-4 rounded-lg min-h-[400px]">
            <h3 className="font-bold text-gray-700 mb-4">TO DO</h3>
            <div className="bg-white p-4 rounded shadow mb-3 cursor-pointer hover:shadow-md transition-shadow">
              <div className="flex justify-between">
                <h4 className="font-semibold">Gửi báo cáo dự án</h4>
                <span className="text-xs text-red-500 font-bold">Hôm nay 17:00</span>
              </div>
              <p className="text-sm text-gray-600 mt-2">Cho team phát triển sản phẩm</p>
              <div className="mt-3 flex gap-2">
                <span className="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded">✨ AI: Báo cáo</span>
              </div>
            </div>
          </div>
          <div className="bg-gray-200 p-4 rounded-lg min-h-[400px]">
            <h3 className="font-bold text-gray-700 mb-4">DOING</h3>
          </div>
          <div className="bg-gray-200 p-4 rounded-lg min-h-[400px]">
            <h3 className="font-bold text-gray-700 mb-4">DONE</h3>
          </div>
        </div>

        <AiAssistant currentNoteId={currentNoteId} page="notes_list" />
      </div>
    </div>
  );
};

function App() {
  return (
    <ThemeProvider>
      <Router>
        <Routes>
          {/* Public marketing site — shown before login/register */}
          <Route path="/" element={<Landing />} />
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          {/* Existing in-app dashboard — unchanged, now mounted at /dashboard */}
          <Route path="/dashboard" element={<Dashboard />} />
        </Routes>
      </Router>
    </ThemeProvider>
  );
}

export default App;
