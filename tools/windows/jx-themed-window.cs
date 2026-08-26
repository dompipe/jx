using System;
using System.Collections.Generic;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Windows.Forms;

namespace Jx.ThemedWindow
{
    internal sealed class ThemedWindow : Form
    {
        private readonly Timer timer = new Timer();
        private readonly List<PointF> trail = new List<PointF>();
        private float phase;
        private bool mashEnabled = true;
        private bool blurTrail = true;
        private bool dottedTrail;

        private ThemedWindow()
        {
            Text = "JX Mash Theme Window";
            Width = 980;
            Height = 660;
            MinimumSize = new Size(720, 480);
            StartPosition = FormStartPosition.CenterScreen;
            DoubleBuffered = true;
            BackColor = Color.FromArgb(18, 22, 30);

            timer.Interval = 16;
            timer.Tick += delegate
            {
                phase += mashEnabled ? 0.011f : 0.004f;
                if (phase > 1f)
                {
                    phase -= 1f;
                    trail.Clear();
                }
                Invalidate();
            };

            KeyDown += OnKeyDown;
            Load += delegate { timer.Start(); };
        }

        [STAThread]
        private static int Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new ThemedWindow());
            return 0;
        }

        private void OnKeyDown(object sender, KeyEventArgs e)
        {
            if (e.KeyCode == Keys.Space)
            {
                mashEnabled = !mashEnabled;
            }
            else if (e.KeyCode == Keys.B)
            {
                blurTrail = !blurTrail;
                if (blurTrail) dottedTrail = false;
            }
            else if (e.KeyCode == Keys.D)
            {
                dottedTrail = !dottedTrail;
                if (dottedTrail) blurTrail = false;
            }
            else if (e.KeyCode == Keys.Escape)
            {
                Close();
            }
        }

        protected override void OnPaint(PaintEventArgs e)
        {
            base.OnPaint(e);
            Graphics g = e.Graphics;
            g.SmoothingMode = SmoothingMode.AntiAlias;
            g.TextRenderingHint = System.Drawing.Text.TextRenderingHint.ClearTypeGridFit;

            RectangleF stage = new RectangleF(44, 78, ClientSize.Width - 88, ClientSize.Height - 154);
            DrawChrome(g, stage);
            DrawPath(g, stage);

            PointF p = PathPoint(stage, phase);
            trail.Add(p);
            while (trail.Count > 96) trail.RemoveAt(0);

            DrawTrail(g);

            float spinClicks = 12f;
            float degrees = 1f + phase;
            float clickStep = (float)Math.Floor((degrees - 1f) * spinClicks);
            float angle = phase * 720f;
            float zoom = mashEnabled ? 1.0f + 0.42f * EaseOut(phase) : 1.0f;

            DrawMashedControl(g, p, angle, zoom, clickStep);
            DrawUi(g, stage, clickStep, zoom);
        }

        private static void DrawChrome(Graphics g, RectangleF stage)
        {
            using (LinearGradientBrush bg = new LinearGradientBrush(
                new RectangleF(0, 0, Math.Max(1, stage.Right + 44), Math.Max(1, stage.Bottom + 76)),
                Color.FromArgb(24, 31, 43),
                Color.FromArgb(8, 11, 18),
                90f))
            {
                g.FillRectangle(bg, 0, 0, stage.Right + 44, stage.Bottom + 76);
            }

            using (Pen p = new Pen(Color.FromArgb(80, 135, 160, 190), 1))
            using (SolidBrush b = new SolidBrush(Color.FromArgb(28, 255, 255, 255)))
            {
                g.FillRectangle(b, stage);
                g.DrawRectangle(p, stage.X, stage.Y, stage.Width, stage.Height);
            }
        }

        private static void DrawPath(Graphics g, RectangleF stage)
        {
            using (GraphicsPath path = MotionPath(stage))
            using (Pen wide = new Pen(Color.FromArgb(32, 0, 245, 255), 14))
            using (Pen core = new Pen(Color.FromArgb(180, 0, 245, 255), 2))
            {
                wide.StartCap = wide.EndCap = LineCap.Round;
                core.StartCap = core.EndCap = LineCap.Round;
                g.DrawPath(wide, path);
                g.DrawPath(core, path);
            }
        }

        private void DrawTrail(Graphics g)
        {
            if (trail.Count < 2) return;
            for (int i = 0; i < trail.Count; i++)
            {
                float t = i / (float)Math.Max(1, trail.Count - 1);
                float size = dottedTrail ? 8f : 6f + 28f * t;
                int alpha = dottedTrail ? 160 : (int)(18 + 90 * t);
                if (blurTrail && i % 3 != 0) continue;
                using (SolidBrush b = new SolidBrush(Color.FromArgb(alpha, 0, 245, 255)))
                {
                    PointF p = trail[i];
                    g.FillEllipse(b, p.X - size / 2f, p.Y - size / 2f, size, size);
                }
            }
        }

        private static void DrawMashedControl(Graphics g, PointF center, float angle, float zoom, float clickStep)
        {
            GraphicsState state = g.Save();
            g.TranslateTransform(center.X, center.Y);
            g.RotateTransform(angle);
            g.ScaleTransform(zoom, zoom);

            using (GraphicsPath body = new GraphicsPath())
            {
                body.AddEllipse(-46, -46, 92, 92);
                using (PathGradientBrush glow = new PathGradientBrush(body))
                {
                    glow.CenterColor = Color.FromArgb(240, 255, 255, 255);
                    glow.SurroundColors = new[] { Color.FromArgb(120, 0, 245, 255) };
                    g.FillPath(glow, body);
                }
                using (Pen rim = new Pen(Color.FromArgb(230, 22, 244, 255), 4))
                {
                    g.DrawPath(rim, body);
                }
            }

            using (SolidBrush knob = new SolidBrush(Color.FromArgb(240, 22, 31, 45)))
            using (Pen pointer = new Pen(Color.FromArgb(255, 255, 214, 79), 6))
            {
                g.FillEllipse(knob, -25, -25, 50, 50);
                pointer.StartCap = LineCap.Round;
                pointer.EndCap = LineCap.Round;
                g.DrawLine(pointer, 0, 0, 0, -38);
            }

            g.ResetTransform();
            g.Restore(state);

            using (SolidBrush b = new SolidBrush(Color.FromArgb(230, 255, 255, 255)))
            {
                g.DrawString("click " + clickStep.ToString("00"), SystemFonts.CaptionFont, b, center.X - 26, center.Y + 58);
            }
        }

        private void DrawUi(Graphics g, RectangleF stage, float clickStep, float zoom)
        {
            using (SolidBrush title = new SolidBrush(Color.White))
            using (SolidBrush muted = new SolidBrush(Color.FromArgb(190, 210, 220, 235)))
            using (SolidBrush green = new SolidBrush(Color.FromArgb(255, 126, 231, 135)))
            {
                g.DrawString("JX themed executable window", new Font(FontFamily.GenericSansSerif, 18, FontStyle.Bold), title, 44, 28);
                g.DrawString("Theme::mash([spinClicks, zoom]) | IMG_BLUR / IMG_DOTTED replacement-image motion", SystemFonts.MessageBoxFont, muted, 46, 56);
                g.DrawString("Space mash " + (mashEnabled ? "on" : "off") + "    B blur trail    D dotted trail    Esc close", SystemFonts.MessageBoxFont, muted, 48, stage.Bottom + 18);
                g.DrawString("degree 1 -> 2: 12 clicks    current click: " + clickStep.ToString("00") + "    zoom: " + zoom.ToString("0.00"), SystemFonts.MessageBoxFont, green, 48, stage.Bottom + 44);
            }
        }

        private static GraphicsPath MotionPath(RectangleF r)
        {
            GraphicsPath path = new GraphicsPath();
            path.AddBezier(
                r.Left + 38, r.Bottom - 74,
                r.Left + r.Width * 0.28f, r.Top + 8,
                r.Left + r.Width * 0.64f, r.Bottom - 12,
                r.Right - 54, r.Top + 96);
            return path;
        }

        private static PointF PathPoint(RectangleF r, float t)
        {
            float u = 1f - t;
            PointF p0 = new PointF(r.Left + 38, r.Bottom - 74);
            PointF p1 = new PointF(r.Left + r.Width * 0.28f, r.Top + 8);
            PointF p2 = new PointF(r.Left + r.Width * 0.64f, r.Bottom - 12);
            PointF p3 = new PointF(r.Right - 54, r.Top + 96);
            float x = u * u * u * p0.X + 3 * u * u * t * p1.X + 3 * u * t * t * p2.X + t * t * t * p3.X;
            float y = u * u * u * p0.Y + 3 * u * u * t * p1.Y + 3 * u * t * t * p2.Y + t * t * t * p3.Y;
            return new PointF(x, y);
        }

        private static float EaseOut(float t)
        {
            return 1f - (float)Math.Pow(1f - t, 3);
        }
    }
}
