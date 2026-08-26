# JX v0.1 Books — Table of Contents

The JX v0.1 books read from the human-facing language down into the engine. The Page Style chapter is shared by both books and belongs at the point where Controls become visible Pages.

## Programming Guide reading order

1. The short map
2. Your first JX memory
3. Rhetorical flow
4. A Book reads like a paragraph
5. Bags: room before writing
6. Delivery: follow the path
7. Controls: describe the idea, not the host
8. **Page Style, Collectors, Anchors, and Bags** — `JX_v0.1_Page_Style.md`
9. PASL: programming above PASM
10. Compile the same thought to different destinations
11. What PASM is doing underneath
12. Libraries
13. Resistant code
14. Complex numbers
15. The browser is a host, not the language
16. Learn by building one small Book
17. The habits JX v0.1 wants to teach
18. Where to read next

The Style chapter sits between Controls and PASL because a new programmer naturally asks two questions in that order:

```text
What is on the Page?
How does it look and line up?
What does it do?
```

That maps to:

```text
Controls -> Style/Layout -> PASL
```

## Engine Manual reading order

1. The stack
2. JX and PASM are not duplicates
3. The original PASM machine
4. PASM bytecode
5. Superinstructions
6. PASL control flow
7. Two PASL layers are converging
8. Compiler grammar stays rhetorical
9. Resistant lowering
10. PASM memory arena
11. JX Bag memory law
12. Frames and segments
13. Hot containers
14. Container benchmark meaning
15. Using PHP's native container strengths
16. Master Table: executable vocabulary
17. Cooperative scheduler
18. Networking and packets
19. Atomics and locks
20. Books and XI/XIP
21. Binding
22. JX host protocol
23. Browser PASM VM
24. Controls are a compiler/host contract
25. **Page Style resolution, Collectors, Anchors, Images, and Transparency** — `JX_v0.1_Page_Style.md`
26. Rhetorical roles can become compiler evidence
27. Libraries should lower with the Book
28. Plugins
29. The gaps that matter most
30. Optimization priorities
31. What "native" should mean
32. The end product

For the engine reader, the Style chapter belongs immediately after the Control host contract because that is where the renderer must resolve:

```text
Control tree
+ Bags
+ Collectors
+ anchor relationships
+ hex colors
+ background images
+ image/background opacity
+ margin / padding / gap
= resolved Page surface
```

Only then should the host emit HTML/CSS, Win32 geometry/drawing, X11 geometry/drawing, or another native surface.

## Shared Page mnemonic

> **Controls say what exists. Bags hold what changes. Collectors gather. Anchors place. Style paints and spaces. PASL makes it act.**
