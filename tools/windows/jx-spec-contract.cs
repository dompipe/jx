using System;
using System.Text;

namespace Jx.SpecContract
{
    internal static class Program
    {
        private const string ImgDotted = "IMG_DOTTED";
        private const string ImgBlur = "IMG_BLUR";

        private static int Main(string[] args)
        {
            string mode = args.Length > 0 ? args[0] : "--contract";
            if (mode == "-h" || mode == "--help")
            {
                Usage();
                return 0;
            }

            if (mode != "--contract" && mode != "--smoke")
            {
                Console.Error.WriteLine("jx-spec-contract: unknown argument " + mode);
                Usage();
                return 2;
            }

            string contract = BuildContract();
            if (mode == "--smoke")
            {
                return Smoke(contract);
            }

            Console.WriteLine(contract);
            return 0;
        }

        private static void Usage()
        {
            Console.WriteLine("jx-spec-contract.exe");
            Console.WriteLine("  --contract   print the compiled JX window/control contract");
            Console.WriteLine("  --smoke      verify required language-spec fields");
        }

        private static int Smoke(string contract)
        {
            string[] required =
            {
                "\"ontology\":\"Book/Page/Bag/Task/Delivery\"",
                "\"host\":\"win32\"",
                "\"family\":\"Control\"",
                "\"family\":\"Image\"",
                "\"mode\":\"" + ImgBlur + "\"",
                "\"alternateMode\":\"" + ImgDotted + "\"",
                "\"paintPoint\":\"XY_RT\"",
                "\"visibleSwitch\":\"image.view\""
            };

            foreach (string needle in required)
            {
                if (!contract.Contains(needle))
                {
                    Console.Error.WriteLine("missing " + needle);
                    return 1;
                }
            }

            Console.WriteLine("jx-spec-contract smoke OK");
            return 0;
        }

        private static string BuildContract()
        {
            Json w = new Json();
            w.ObjStart();
            w.Prop("language", "jx");
            w.Prop("pronunciation", "jinx");
            w.Prop("ontology", "Book/Page/Bag/Task/Delivery");
            w.Prop("host", "win32");
            w.Prop("abi", "jx_host:1");
            w.Name("window");
            w.ObjStart();
            w.Prop("id", "language-controls");
            w.Prop("title", "JX Language Controls");
            w.Prop("book", "language");
            w.Prop("page", "controls");
            w.Prop("x", 80);
            w.Prop("y", 80);
            w.Prop("width", 920);
            w.Prop("height", 620);
            w.ObjEnd();
            w.Name("controls");
            w.ArrayStart();
            Toggle(w);
            ImageControl(w);
            DrawingControl(w);
            w.ArrayEnd();
            w.ObjEnd();
            return w.ToString();
        }

        private static void Toggle(Json w)
        {
            w.ObjStart();
            w.Prop("family", "Control");
            w.Prop("type", "toggle");
            w.Prop("id", "image.view");
            w.Prop("label", "Image view switch");
            w.Prop("value", true);
            w.ObjEnd();
        }

        private static void ImageControl(Json w)
        {
            w.ObjStart();
            w.Prop("family", "Control");
            w.Prop("type", "image");
            w.Prop("id", "image.any");
            w.Prop("mime", "image/*");
            w.Name("display");
            w.ObjStart();
            w.Prop("visibleSwitch", "image.view");
            w.Prop("blurWhenHidden", 8);
            w.Prop("coverWhenHidden", true);
            w.ObjEnd();
            w.Name("pin");
            w.ObjStart();
            w.Prop("turningPoint", "XY_CENTER");
            w.Prop("pathPoint", "XY_LB");
            w.Prop("paintPoint", "XY_RT");
            w.Name("paintControl");
            LineWithImage(w, "image-trail", 16, 42, 220, 42, false, ImgBlur, 8);
            w.ObjEnd();
            w.ObjEnd();
        }

        private static void DrawingControl(Json w)
        {
            w.ObjStart();
            w.Prop("family", "Control");
            w.Prop("type", "drawing");
            w.Prop("id", "drawing.surface");
            w.Prop("smooth", 0.82);
            w.Name("ops");
            w.ArrayStart();
            LineWithImage(w, "dotted-path", 24, 108, 336, 88, true, ImgDotted, 24);
            w.ArrayEnd();
            w.ObjEnd();
        }

        private static void LineWithImage(Json w, string id, int x1, int y1, int x2, int y2, bool pong, string mode, int spacing)
        {
            w.ObjStart();
            w.Prop("family", "Control");
            w.Prop("op", "line");
            w.Prop("refId", id);
            w.Prop("pong", pong);
            w.Name("start");
            w.ObjStart();
            w.Prop("x", x1);
            w.Prop("y", y1);
            w.ObjEnd();
            w.Name("finish");
            w.ObjStart();
            w.Prop("x", x2);
            w.Prop("y", y2);
            w.ObjEnd();
            w.Name("image");
            w.ObjStart();
            w.Prop("family", "Image");
            w.Prop("kind", "img");
            w.Prop("filename", mode == ImgBlur ? "neon-line.png" : "spark.png");
            w.Prop("mime", "image/png");
            w.Prop("mode", mode);
            w.Prop(mode == ImgBlur ? "every" : "spacing", spacing);
            w.Prop("alternateMode", mode == ImgBlur ? ImgDotted : ImgBlur);
            w.ObjEnd();
            w.ObjEnd();
        }

        private sealed class Json
        {
            private readonly StringBuilder text = new StringBuilder();
            private readonly System.Collections.Generic.Stack<bool> first = new System.Collections.Generic.Stack<bool>();
            private bool expectingValue;

            public override string ToString()
            {
                return text.ToString();
            }

            public void ObjStart()
            {
                Prefix();
                text.Append('{');
                first.Push(true);
                expectingValue = false;
            }

            public void ObjEnd()
            {
                text.Append('}');
                first.Pop();
            }

            public void ArrayStart()
            {
                Prefix();
                text.Append('[');
                first.Push(true);
                expectingValue = false;
            }

            public void ArrayEnd()
            {
                text.Append(']');
                first.Pop();
            }

            public void Name(string name)
            {
                Prefix();
                Str(name);
                text.Append(':');
                expectingValue = true;
            }

            public void Prop(string name, string value)
            {
                Name(name);
                Str(value);
            }

            public void Prop(string name, int value)
            {
                Name(name);
                expectingValue = false;
                text.Append(value);
            }

            public void Prop(string name, double value)
            {
                Name(name);
                expectingValue = false;
                text.Append(value.ToString(System.Globalization.CultureInfo.InvariantCulture));
            }

            public void Prop(string name, bool value)
            {
                Name(name);
                expectingValue = false;
                text.Append(value ? "true" : "false");
            }

            private void Prefix()
            {
                if (expectingValue)
                {
                    expectingValue = false;
                    return;
                }

                if (first.Count == 0)
                {
                    return;
                }

                bool isFirst = first.Pop();
                if (!isFirst)
                {
                    text.Append(',');
                }
                first.Push(false);
            }

            private void Str(string value)
            {
                expectingValue = false;
                text.Append('"');
                foreach (char c in value)
                {
                    switch (c)
                    {
                        case '\\':
                            text.Append("\\\\");
                            break;
                        case '"':
                            text.Append("\\\"");
                            break;
                        case '\n':
                            text.Append("\\n");
                            break;
                        case '\r':
                            text.Append("\\r");
                            break;
                        case '\t':
                            text.Append("\\t");
                            break;
                        default:
                            text.Append(c);
                            break;
                    }
                }
                text.Append('"');
            }
        }
    }
}
