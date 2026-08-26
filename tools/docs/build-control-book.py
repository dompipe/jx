from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import letter
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import inch
from reportlab.platypus import (
    PageBreak,
    Paragraph,
    Preformatted,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)


ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "output" / "pdf" / "JX_Control_Families_Book.pdf"


def styles():
    base = getSampleStyleSheet()
    base.add(ParagraphStyle(
        name="CoverTitle",
        parent=base["Title"],
        alignment=TA_CENTER,
        fontSize=28,
        leading=34,
        spaceAfter=24,
    ))
    base.add(ParagraphStyle(
        name="CoverSub",
        parent=base["Normal"],
        alignment=TA_CENTER,
        fontSize=12,
        leading=18,
        textColor=colors.HexColor("#374151"),
    ))
    base.add(ParagraphStyle(
        name="Chapter",
        parent=base["Heading1"],
        fontSize=18,
        leading=24,
        spaceBefore=12,
        spaceAfter=12,
        textColor=colors.HexColor("#111827"),
    ))
    base.add(ParagraphStyle(
        name="Section",
        parent=base["Heading2"],
        fontSize=12,
        leading=16,
        spaceBefore=10,
        spaceAfter=6,
        textColor=colors.HexColor("#1f2937"),
    ))
    base.add(ParagraphStyle(
        name="Body",
        parent=base["BodyText"],
        fontSize=9.6,
        leading=13.2,
        spaceAfter=7,
    ))
    base.add(ParagraphStyle(
        name="Small",
        parent=base["BodyText"],
        fontSize=8.2,
        leading=11.2,
        textColor=colors.HexColor("#374151"),
    ))
    base.add(ParagraphStyle(
        name="CodeBlock",
        parent=base["Code"],
        fontName="Courier",
        fontSize=7.4,
        leading=9.2,
        leftIndent=8,
        rightIndent=8,
        spaceBefore=4,
        spaceAfter=8,
        backColor=colors.HexColor("#f3f4f6"),
        borderColor=colors.HexColor("#e5e7eb"),
        borderWidth=0.5,
        borderPadding=5,
    ))
    return base


def p(text, style):
    return Paragraph(text, style)


def code(text, style):
    return Preformatted(text.strip(), style)


def term_table(rows, style):
    data = [[p("<b>Term</b>", style["Small"]), p("<b>Meaning</b>", style["Small"])]]
    for term, meaning in rows:
        data.append([p(f"<b>{term}</b>", style["Small"]), p(meaning, style["Small"])])
    table = Table(data, colWidths=[1.55 * inch, 4.85 * inch], hAlign="LEFT")
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#e5e7eb")),
        ("GRID", (0, 0), (-1, -1), 0.35, colors.HexColor("#d1d5db")),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
    ]))
    return table


CHAPTERS = [
    {
        "title": "Family Map",
        "preface": "JX controls are host-neutral contracts, not browser-only widgets. The family map keeps the language appendable as it grows toward native windows, X11 relevance, and a JX window server.",
        "terms": [
            ("family", "Top-level taxonomy owner for a contract object."),
            ("Control", "Host controls and drawing operations."),
            ("Image", "Image leaves, replacement sets, repeated path images, and trade-offs."),
            ("Theme", "Spin, zoom, and mash movement composition."),
            ("Run", "Percent-window behavior such as pong and reset."),
            ("Output", "Callback connector with a movement property bag."),
        ],
        "body": [
            "Every contract is designed to survive renderer changes. A browser can render it today, a Win32 host can render it now, and a later JX window server can own it without changing the Book model.",
            "Future chapters should append to the end of this book and add one line to the running table of contents.",
        ],
        "placebo": "control image.any uses Image, Theme, Run, and Output\nhost renders it as browser, Win32, X11, or JX window server",
        "tight": "Control::image('image.any', 'Any image type', $src, 'image/*', [\n    'pin' => Control::imagePin(Control::XY_CENTER, Control::XY_LB, Control::XY_RT),\n]);",
        "advanced": "Advanced technique: keep semantic families stable, then allow each host to optimize rendering locally. The same `data-control` JSON can become HTML attributes, Win32 draw calls, or future compositor messages.",
    },
    {
        "title": "Control Family",
        "preface": "Control is the surface family. It gives Books a way to declare inputs, switches, image controls, and drawing surfaces without binding the Book to a particular OS toolkit.",
        "terms": [
            ("text", "Normal form input."),
            ("spin", "Numeric spinner or dial-like stepper."),
            ("toggle", "Boolean switch control."),
            ("drawing", "Host-renderable drawing surface."),
            ("image", "MIME/source-backed image control."),
            ("pin", "A point or setting that anchors a control to motion or host layout."),
        ],
        "body": [
            "A control has `id`, `type`, `props`, and `events`. The HTML renderer exposes the contract through `data-control`; native hosts can read the PHP `contract()` array directly.",
            "Controls can carry image replacement sets and themes. That allows a spin control to be a dial image in one host and a native number spinner in another.",
        ],
        "placebo": "make a spin control\npin it\ngive it a dial image set and a spin theme",
        "tight": "$spinTheme = Theme::spinClicks('spin.rate', 1, 2, 12, ['wrap' => true]);\n\nControl::spin('spin.rate', 'Spin control', 3, [\n    'min' => -12,\n    'max' => 12,\n    'step' => 1,\n    'pin' => true,\n    'theme' => $spinTheme,\n]);",
        "advanced": "Advanced technique: correlate a visible control with an event-sourced output bag. The control remains a contract while the host chooses whether to render native input, an image replacement, or a themed custom surface.",
    },
    {
        "title": "Image Family",
        "preface": "Image is a full family because controls need replaceable, repeatable, and event-sourced image behavior. A neon trail is not a special line type; it is a line carrying an image brush.",
        "terms": [
            ("img", "One image leaf."),
            ("IMG_DOTTED", "Repeat the same image one after another along a path."),
            ("IMG_BLUR", "Repeat every x pixels for a blur or speed trail."),
            ("replacementSet", "State-indexed images for dials, buttons, and switches."),
            ("tradeOff", "Event record describing one image changing to another."),
            ("ROLE_DIAL", "Replacement-set role for dial controls."),
            ("ROLE_BUTTON", "Replacement-set role for button controls."),
            ("ROLE_SWITCH", "Replacement-set role for switch controls."),
        ],
        "body": [
            "Image controls accept any MIME-backed image source. SVG is supported but not special.",
            "Replacement images let a control trade visual state without changing the control identity. Trade-offs should be consumed as events, not inferred from markup.",
        ],
        "placebo": "use neon-line.png as a repeated paint image\nwhen image.view changes, trade button-up for button-disabled",
        "tight": "Image::blur('neon-line.png', 'image/png', 8, [\n    'role' => 'paint',\n    'glow' => 0.9,\n]);\n\nImage::tradeOff(\n    'evt-image-view-toggle',\n    'control.image.view.changed',\n    Image::img('controls/button-up.png', 'image/png'),\n    Image::img('controls/button-disabled.png', 'image/png'),\n    'View display switched off',\n);",
        "advanced": "Advanced technique: image replacement sets can be shared by controls and executable hosts. The compiled Windows proof includes dial, switch, and button replacement sets in JSON.",
    },
    {
        "title": "Theme Family",
        "preface": "Theme owns motion behavior that can be reused across controls, lines, and curves. This lets the host mash spin and zoom into one movement without losing the inner contracts.",
        "terms": [
            ("spinClicks", "Maps a degree span to a number of clicks."),
            ("zoom", "Maps one scale to another with easing."),
            ("mash", "Combines several theme motions into one behavior."),
            ("clicksPerDegree", "Derived ratio used for click and dial handling."),
            ("wrap", "Host hint that spin can wrap through its range."),
        ],
        "body": [
            "`Theme::mash([$spinTheme, $zoomTheme])` has no extra name or mode. The kind is `mash`; the motions array holds the inner behavior.",
            "The themed executable uses this to spin, move, and zoom a dial-like image control along a curve.",
        ],
        "placebo": "between degree 1 and degree 2, consume 12 clicks\nwhile moving, zoom from 1.0 to 1.35\nmash both motions together",
        "tight": "$spinTheme = Theme::spinClicks('spin.rate', 1, 2, 12, ['wrap' => true]);\n$zoomTheme = Theme::zoom(1.0, 1.35, 'ease-out');\n$mashTheme = Theme::mash([$spinTheme, $zoomTheme]);",
        "advanced": "Advanced technique: when a host receives pointer input, it can convert path phase into spin clicks, zoom scale, and image replacement state in one callback cycle.",
    },
    {
        "title": "Drawing And Path Family",
        "preface": "Drawing is the shape side of controls. It supports simple shapes, curved shapes, movement curves, and SVG path evocation for host-native path APIs.",
        "terms": [
            ("line", "Straight movement or drawing line."),
            ("curve", "Movement path defined by degree/control points."),
            ("polygon", "Closed straight-edged shape."),
            ("curvedShape", "Closed curved shape from points."),
            ("path", "SVG-style path evoker."),
            ("smooth", "Interpolation property from 0 to 1."),
            ("path evoker", "A preserved path string the host maps to its own path API."),
        ],
        "body": [
            "`curve()` is for movement. `path()` is for path drawing or path evocation. Hosts may use the same graphics backend for both, but the contract keeps their meanings separate.",
            "The current HTML renderer emits SVG. The native executable uses a WinForms graphics path to prove the same direction outside the browser.",
        ],
        "placebo": "draw a polygon\ndraw a curved shape\nevocate an SVG-style path\nmove a control along a curve",
        "tight": "Control::polygon('triangle-shape', [\n    ['x' => 258, 'y' => 24],\n    ['x' => 334, 'y' => 76],\n    ['x' => 280, 'y' => 124],\n]);\n\nControl::path(\n    'svg-evoker',\n    'M 40 42 C 76 6 118 74 156 42 S 238 78 316 42',\n    ['fill' => 'none', 'stroke' => '#be185d'],\n);",
        "advanced": "Advanced technique: click-to-seek maps pointer location to the nearest curve phase, then updates movement, spin, zoom, trail, and callback output.",
    },
    {
        "title": "Run Family",
        "preface": "Run describes how a movement proceeds over a percent window. It is the difference between bouncing back and jumping back.",
        "terms": [
            ("pong", "Move forward and then backward."),
            ("reset", "Move forward and jump back to start."),
            ("start", "Percent where movement begins."),
            ("end", "Percent where movement completes."),
            ("percent window", "The active part of a full path run."),
        ],
        "body": [
            "Default run window is 0..1. A run window of 0.25..0.75 uses only the middle half of the path.",
            "Legacy boolean `true` still normalizes to `Control::pong(0, 1)`, but new code should use explicit run constructors.",
        ],
        "placebo": "run the middle half of the line\nwhen reaching 75 percent, jump back to 25 percent",
        "tight": "Control::line(\n    'reset-line',\n    ['x' => 42, 'y' => 166],\n    ['x' => 318, 'y' => 166],\n    Control::reset(0.25, 0.75),\n);",
        "advanced": "Advanced technique: percent windows let a host reuse one full geometry while multiple controls occupy different spans of it.",
    },
    {
        "title": "Output Callback Family",
        "preface": "Movement can produce output where the user picked it. The output connector names the callback and carries the property bag.",
        "terms": [
            ("output", "Callback connector on a movement."),
            ("callback", "Host event or function name to call."),
            ("at", "Screen anchor for output placement."),
            ("bag", "Movement property payload."),
            ("picked", "User or host-selected movement point."),
        ],
        "body": [
            "When a user clicks a path, the host can call the output connector with position, phase, run window, and the supplied bag.",
            "The themed executable shows this as a small callback bag panel near the picked point.",
        ],
        "placebo": "when reset-line is picked\nshow output at XY_RT\ncall controls.movementPicked with the movement bag",
        "tight": "Control::line(\n    'reset-line',\n    ['x' => 42, 'y' => 166],\n    ['x' => 318, 'y' => 166],\n    Control::reset(0.25, 0.75),\n) + [\n    'output' => Control::output(\n        'controls.movementPicked',\n        Control::XY_RT,\n        ['source' => 'reset-line', 'run' => 'reset'],\n    ),\n];",
        "advanced": "Advanced technique: output callbacks make controls feel native because the output appears where the action happened, not in a detached log.",
    },
    {
        "title": "Executable Proofs",
        "preface": "The executable proofs show that the language direction is not only server-rendered markup. The contracts can be compiled into native Windows executables.",
        "terms": [
            ("jx-spec-contract.exe", "Compiled JSON contract proof."),
            ("jx-themed-window.exe", "Native WinForms visual proof."),
            ("click-to-seek", "Clicking near the path moves control to that phase."),
            ("callback bag panel", "Visible output of picked movement properties."),
        ],
        "body": [
            "`jx-spec-contract.exe` emits JSON with Control, Image, Theme, Run, Drawing, Path, and Output sections.",
            "`jx-themed-window.exe` draws a native window with mashed motion, click-to-seek, blur/dotted trails, a reset runner, and output bags.",
        ],
        "placebo": "build native proof\nshow mashed image control\nclick path\nemit property bag where picked",
        "tight": "tools\\windows\\build-spec-contract.ps1\nbuild\\windows\\jx-spec-contract.exe --smoke\nbuild\\windows\\jx-spec-contract.exe --contract\n\ntools\\windows\\build-themed-window.ps1\nbuild\\windows\\jx-themed-window.exe",
        "advanced": "Advanced technique: the executable can be treated as a temporary native host while JX grows toward a larger window-server architecture.",
    },
]


def build():
    OUT.parent.mkdir(parents=True, exist_ok=True)
    tmp = ROOT / "tmp" / "pdfs"
    tmp.mkdir(parents=True, exist_ok=True)
    s = styles()
    doc = SimpleDocTemplate(
        str(OUT),
        pagesize=letter,
        rightMargin=0.72 * inch,
        leftMargin=0.72 * inch,
        topMargin=0.68 * inch,
        bottomMargin=0.62 * inch,
        title="JX Control Families Book",
        author="JX",
    )
    story = []
    story.append(Spacer(1, 1.2 * inch))
    story.append(p("JX Control Families Book", s["CoverTitle"]))
    story.append(p("End-to-end coverage of Control, Image, Theme, Run, Drawing, Path, Output, and native executable proof contracts.", s["CoverSub"]))
    story.append(Spacer(1, 0.35 * inch))
    story.append(p("Direction: append future chapters at the end and append the same chapter to the running table of contents.", s["CoverSub"]))
    story.append(PageBreak())

    story.append(p("Running Table Of Contents", s["Chapter"]))
    toc_rows = [["Chapter", "Family Section"]]
    for i, ch in enumerate(CHAPTERS, 1):
        toc_rows.append([str(i), ch["title"]])
    toc_rows.append(["Appendix A", "Current Term Tally"])
    table = Table(toc_rows, colWidths=[1.2 * inch, 5.2 * inch], hAlign="LEFT")
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#111827")),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("GRID", (0, 0), (-1, -1), 0.35, colors.HexColor("#d1d5db")),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
    ]))
    story.append(table)
    story.append(Spacer(1, 0.2 * inch))
    story.append(p("Append rule: add new family chapters after the last chapter, then add one new TOC row. Existing meanings should get modification notes instead of silent rewrites.", s["Body"]))
    story.append(PageBreak())

    for i, ch in enumerate(CHAPTERS, 1):
        story.append(p(f"Chapter {i}: {ch['title']}", s["Chapter"]))
        story.append(p("Chapter Preface", s["Section"]))
        story.append(p(ch["preface"], s["Body"]))
        story.append(p("Chapter Terminology Appendix", s["Section"]))
        story.append(term_table(ch["terms"], s))
        story.append(Spacer(1, 0.08 * inch))
        story.append(p("Inner Functionality", s["Section"]))
        for para in ch["body"]:
            story.append(p(para, s["Body"]))
        story.append(p("Placebo Code", s["Section"]))
        story.append(code(ch["placebo"], s["CodeBlock"]))
        story.append(p("Tight Version", s["Section"]))
        story.append(code(ch["tight"], s["CodeBlock"]))
        story.append(p("Advanced Techniques", s["Section"]))
        story.append(p(ch["advanced"], s["Body"]))
        story.append(p("Modifications And Meanings", s["Section"]))
        story.append(p("This chapter is append-friendly. Add new functions as new rows in the terminology appendix, then add placebo and tight examples below the existing examples.", s["Body"]))
        story.append(PageBreak())

    story.append(p("Appendix A: Current Term Tally", s["Chapter"]))
    tallies = [
        ("Families", "Control, Image, Theme, Run, Output"),
        ("Control terms", "text, spin, toggle, drawing, image, pin, imagePin, paintPoint, imageDisplay, polygon, curvedShape, path, curve, line"),
        ("Image terms", "img, IMG_DOTTED, IMG_BLUR, replacementSet, tradeOff, ROLE_DIAL, ROLE_BUTTON, ROLE_SWITCH"),
        ("Theme terms", "spinClicks, zoom, mash, clicksPerDegree"),
        ("Run terms", "pong, reset, start, end, percent window"),
        ("Output terms", "output, callback, at, bag, picked"),
        ("Executable terms", "jx-spec-contract.exe, jx-themed-window.exe, click-to-seek, callback bag panel"),
    ]
    story.append(term_table(tallies, s))
    story.append(Spacer(1, 0.15 * inch))
    story.append(p("Appendix maintenance rule: whenever a new term appears in a chapter, add it to this tally. This keeps the language family map visible as the book grows.", s["Body"]))

    def page(canvas, document):
        canvas.saveState()
        canvas.setFont("Helvetica", 8)
        canvas.setFillColor(colors.HexColor("#6b7280"))
        canvas.drawString(0.72 * inch, 0.38 * inch, "JX Control Families Book")
        canvas.drawRightString(letter[0] - 0.72 * inch, 0.38 * inch, f"Page {document.page}")
        canvas.restoreState()

    doc.build(story, onFirstPage=page, onLaterPages=page)
    print(OUT)


if __name__ == "__main__":
    build()
