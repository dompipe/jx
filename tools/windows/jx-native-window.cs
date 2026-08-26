using System;
using System.Drawing;
using System.Net;
using System.Text;
using System.Text.RegularExpressions;
using System.Windows.Forms;

internal sealed class JxNativeWindow : Form
{
    private readonly string _url;
    private readonly string _book;
    private readonly WebClient _client = new WebClient();
    private readonly TextBox _content = new TextBox();
    private readonly Label _status = new Label();

    private JxNativeWindow(string url, string book)
    {
        _url = url;
        _book = book;
        Text = "JX Book - " + book;
        Width = 900;
        Height = 680;
        StartPosition = FormStartPosition.CenterScreen;

        var bar = new FlowLayoutPanel();
        bar.Dock = DockStyle.Top;
        bar.Height = 42;
        bar.Padding = new Padding(8, 7, 8, 7);

        var refresh = new Button();
        refresh.Text = "Refresh";
        refresh.Width = 90;
        refresh.Click += delegate { LoadBook(); };

        var back = new Button();
        back.Text = "Back";
        back.Width = 80;
        back.Click += delegate { Turn("back"); };

        var forward = new Button();
        forward.Text = "Forward";
        forward.Width = 90;
        forward.Click += delegate { Turn("forward"); };

        _status.AutoSize = true;
        _status.Padding = new Padding(12, 6, 0, 0);

        bar.Controls.Add(refresh);
        bar.Controls.Add(back);
        bar.Controls.Add(forward);
        bar.Controls.Add(_status);

        _content.Dock = DockStyle.Fill;
        _content.Multiline = true;
        _content.ReadOnly = true;
        _content.ScrollBars = ScrollBars.Vertical;
        _content.Font = new Font(FontFamily.GenericMonospace, 11);
        _content.BackColor = Color.White;

        Controls.Add(_content);
        Controls.Add(bar);
        Load += delegate { LoadBook(); };
    }

    [STAThread]
    private static int Main(string[] args)
    {
        string url = args.Length >= 1 ? args[0] : "http://127.0.0.1:8766/?book=cover";
        string book = args.Length >= 2 ? args[1] : "cover";
        Application.EnableVisualStyles();
        Application.SetCompatibleTextRenderingDefault(false);
        Application.Run(new JxNativeWindow(url, book));
        return 0;
    }

    private void LoadBook()
    {
        try
        {
            string html = _client.DownloadString(_url);
            _content.Text = PlainText(html);
            _status.Text = _url;
        }
        catch (Exception ex)
        {
            _content.Text = "JX native window error:" + Environment.NewLine + ex.Message;
            _status.Text = "error";
        }
    }

    private void Turn(string dir)
    {
        try
        {
            var data = Encoding.UTF8.GetBytes("book=" + Uri.EscapeDataString(_book) + "&protocol=book.turn&dir=" + dir);
            _client.Headers[HttpRequestHeader.ContentType] = "application/x-www-form-urlencoded";
            string endpoint = Regex.Replace(_url, "\\?.*$", "/");
            string html = _client.UploadString(endpoint, "POST", Encoding.UTF8.GetString(data));
            _content.Text = PlainText(html);
            _status.Text = dir;
        }
        catch (Exception ex)
        {
            _content.Text = "JX native window error:" + Environment.NewLine + ex.Message;
            _status.Text = "error";
        }
    }

    private static string PlainText(string html)
    {
        html = Regex.Replace(html, "<script[\\s\\S]*?</script>", "", RegexOptions.IgnoreCase);
        html = Regex.Replace(html, "<style[\\s\\S]*?</style>", "", RegexOptions.IgnoreCase);
        html = Regex.Replace(html, "</(h[1-6]|p|div|section|article|li|tr|form)>", Environment.NewLine, RegexOptions.IgnoreCase);
        html = Regex.Replace(html, "<br\\s*/?>", Environment.NewLine, RegexOptions.IgnoreCase);
        html = Regex.Replace(html, "<[^>]+>", " ");
        html = WebUtility.HtmlDecode(html);
        html = Regex.Replace(html, "[ \\t]+", " ");
        html = Regex.Replace(html, "(\\r?\\n\\s*){3,}", Environment.NewLine + Environment.NewLine);
        return html.Trim();
    }
}
