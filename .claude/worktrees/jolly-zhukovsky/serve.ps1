Add-Type -TypeDefinition @'
using System;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Text;
using System.Threading;
using System.Collections.Generic;

public class TinyServer {
    static string root;
    static Dictionary<string,string> mime = new Dictionary<string,string>{
        {".html","text/html; charset=utf-8"},
        {".css","text/css; charset=utf-8"},
        {".js","application/javascript"},
        {".png","image/png"},
        {".jpg","image/jpeg"},
        {".svg","image/svg+xml"},
        {".json","application/json"},
        {".ico","image/x-icon"},
        {".woff2","font/woff2"},
        {".woff","font/woff"},
        {".ttf","font/ttf"}
    };
    static void HandleClient(object obj) {
        TcpClient client = (TcpClient)obj;
        try {
            NetworkStream ns = client.GetStream();
            byte[] buf = new byte[8192];
            int n = ns.Read(buf, 0, buf.Length);
            string req = Encoding.ASCII.GetString(buf, 0, n);
            string[] lines = req.Split('\n');
            string[] parts = lines[0].Trim().Split(' ');
            string path = parts.Length > 1 ? parts[1] : "/";
            int qmark = path.IndexOf('?');
            if (qmark >= 0) path = path.Substring(0, qmark);
            path = Uri.UnescapeDataString(path);
            if (path == "/") path = "/index.html";
            string file = root + path.Replace('/', Path.DirectorySeparatorChar);
            if (File.Exists(file)) {
                byte[] data = File.ReadAllBytes(file);
                string ext = Path.GetExtension(file).ToLower();
                string ct = mime.ContainsKey(ext) ? mime[ext] : "application/octet-stream";
                string header = "HTTP/1.1 200 OK\r\nContent-Type: " + ct + "\r\nContent-Length: " + data.Length + "\r\nConnection: close\r\n\r\n";
                byte[] hb = Encoding.ASCII.GetBytes(header);
                ns.Write(hb, 0, hb.Length);
                ns.Write(data, 0, data.Length);
            } else {
                byte[] body = Encoding.UTF8.GetBytes("Not found");
                string header = "HTTP/1.1 404 Not Found\r\nContent-Type: text/plain\r\nContent-Length: " + body.Length + "\r\nConnection: close\r\n\r\n";
                byte[] hb = Encoding.ASCII.GetBytes(header);
                ns.Write(hb, 0, hb.Length);
                ns.Write(body, 0, body.Length);
            }
            ns.Flush();
        } catch {}
        finally { client.Close(); }
    }
    public static void Run(string rootPath, int port) {
        root = rootPath;
        TcpListener listener = new TcpListener(IPAddress.Loopback, port);
        listener.Start();
        Console.WriteLine("Serving " + rootPath + " on http://127.0.0.1:" + port + "/");
        Console.Out.Flush();
        while (true) {
            TcpClient client = listener.AcceptTcpClient();
            ThreadPool.QueueUserWorkItem(HandleClient, client);
        }
    }
}
'@

[TinyServer]::Run('C:\Users\krzys\OneDrive\Dokumenty\flowquest.pl', 3000)
