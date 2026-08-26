using System;
using System.Diagnostics;
using System.IO;
using System.Linq;

internal static class JxWindows
{
    private const string CompiledRoot = "";

    private static int Main(string[] args)
    {
        string root = FindRoot();
        string jxRun = Path.Combine(root, "jx-run.php");
        string windowServer = Path.Combine(root, "jx-window-server.php");
        string xi = Path.Combine(root, "pasl", "xi", "xi.php");

        if (args.Length == 0 || args[0] == "-h" || args[0] == "--help")
        {
            Usage();
            return 0;
        }

        if (args[0] == "window-server" || args[0] == "windows")
        {
            return RunPhp(windowServer, args.Skip(1).ToArray());
        }

        if (args[0] == "xi")
        {
            return RunPhp(xi, args.Skip(1).ToArray());
        }

        if (args.Length >= 2 && args[0] == "book" && args[1] == "open")
        {
            string book = args.Length >= 3 ? args[2] : "cover";
            string hostport = args.Length >= 4 ? args[3] : "localhost:8766";
            Console.WriteLine("jx: opening Book {0} at http://{1}/?book={0}", book, hostport);
            return RunPhp(windowServer, new[] { "open", book, hostport, "--native" });
        }

        return RunPhp(jxRun, args);
    }

    private static void Usage()
    {
        Console.WriteLine("jx Windows native launcher");
        Console.WriteLine();
        Console.WriteLine("Usage:");
        Console.WriteLine("  jx.exe [jx-run args...]");
        Console.WriteLine("  jx.exe window-server <start|stop|status|open> [...]");
        Console.WriteLine("  jx.exe xi <host:port> <start|stop|status> [config.json] [--foreground]");
        Console.WriteLine("  jx.exe book open [book] [host:port]");
        Console.WriteLine();
        Console.WriteLine("Examples:");
        Console.WriteLine("  jx.exe --print examples\\hello.jx");
        Console.WriteLine("  jx.exe window-server status localhost:8766");
        Console.WriteLine("  jx.exe xi localhost:8766 status");
        Console.WriteLine("  jx.exe book open language localhost:8766");
    }

    private static string FindRoot()
    {
        string env = Environment.GetEnvironmentVariable("JX_ROOT");
        if (!string.IsNullOrWhiteSpace(env) && ValidRoot(env))
        {
            return Path.GetFullPath(env);
        }

        if (!string.IsNullOrWhiteSpace(CompiledRoot) && ValidRoot(CompiledRoot))
        {
            return Path.GetFullPath(CompiledRoot);
        }

        ProcessModule module = Process.GetCurrentProcess().MainModule;
        string exe = module != null ? module.FileName : Environment.GetCommandLineArgs()[0];
        string dir = Path.GetDirectoryName(exe);
        if (!string.IsNullOrEmpty(dir))
        {
            string candidate = Path.GetFullPath(Path.Combine(dir, ".."));
            if (ValidRoot(candidate))
            {
                return candidate;
            }
        }

        string cwd = Directory.GetCurrentDirectory();
        if (ValidRoot(cwd))
        {
            return cwd;
        }

        throw new InvalidOperationException("jx: cannot locate JX root; set JX_ROOT");
    }

    private static bool ValidRoot(string root)
    {
        return File.Exists(Path.Combine(root, "jx-run.php"))
            && File.Exists(Path.Combine(root, "pasl", "xi", "xi.php"));
    }

    private static int RunPhp(string script, string[] args)
    {
        string arguments = Quote(script);
        foreach (string arg in args)
        {
            arguments += " " + Quote(arg);
        }

        var start = new ProcessStartInfo
        {
            FileName = "php",
            Arguments = arguments,
            UseShellExecute = false
        };

        using (Process process = Process.Start(start))
        {
            if (process == null)
            {
                throw new InvalidOperationException("jx: failed to start php");
            }
            process.WaitForExit();
            return process.ExitCode;
        }
    }

    private static string Quote(string value)
    {
        return "\"" + value.Replace("\\", "\\\\").Replace("\"", "\\\"") + "\"";
    }
}
