using System;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Text;
using System.Threading;

class S {
    static string root = @"C:\Users\krzys\OneDrive\Dokumenty\flowquest.pl";
    static string Mime(string ext) {
        switch(ext) {
            case ".html": return "text/html; charset=utf-8";
            case ".css":  return "text/css; charset=utf-8";
            case ".js":   return "application/javascript";
            case ".png":  return "image/png";
            case ".jpg": case ".jpeg": return "image/jpeg";
            case ".svg":  return "image/svg+xml";
            case ".ico":  return "image/x-icon";
            case ".json": return "application/json";
            case ".woff2": return "font/woff2";
            case ".woff":  return "font/woff";
            default: return "application/octet-stream";
        }
    }
    static void Handle(object o) {
        TcpClient c = (TcpClient)o;
        try {
            NetworkStream ns = c.GetStream();
            byte[] buf = new byte[8192]; int n = ns.Read(buf,0,buf.Length);
            string req = Encoding.ASCII.GetString(buf,0,n);
            string[] parts = req.Split('\n')[0].Trim().Split(' ');
            string path = parts.Length>1?parts[1]:"/";
            int q = path.IndexOf('?'); if(q>=0) path=path.Substring(0,q);
            path = Uri.UnescapeDataString(path).Replace('/',Path.DirectorySeparatorChar);
            if(path==@"\") path=@"\index.html";
            string file = root + path;
            byte[] data; string status; string ct;
            if(File.Exists(file)) {
                data=File.ReadAllBytes(file);
                status="200 OK";
                ct=Mime(Path.GetExtension(file).ToLower());
            } else {
                data=Encoding.UTF8.GetBytes("404 Not Found");
                status="404 Not Found"; ct="text/plain";
            }
            string hdr="HTTP/1.1 "+status+"\r\nContent-Type: "+ct+"\r\nContent-Length: "+data.Length+"\r\nAccess-Control-Allow-Origin: *\r\nConnection: close\r\n\r\n";
            byte[] hb=Encoding.ASCII.GetBytes(hdr);
            ns.Write(hb,0,hb.Length); ns.Write(data,0,data.Length); ns.Flush();
        } catch {}
        finally { c.Close(); }
    }
    static void Main() {
        var l = new TcpListener(IPAddress.Loopback, 3000);
        l.Start();
        Console.WriteLine("http://127.0.0.1:3000/");
        Console.Out.Flush();
        while(true) { ThreadPool.QueueUserWorkItem(Handle, l.AcceptTcpClient()); }
    }
}
