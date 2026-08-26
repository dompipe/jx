# JX v0.1 Books — Table of Contents

The JX v0.1 books read from the human-facing language down into the engine. Shared chapters are placed where the same concept naturally crosses both the programming and engine views.

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
16. **Web Hosting: Apache outside, JX inside** — `JX_v0.1_Web_Hosting.md`
17. **SQL as a first-class JX object** — `JX_v0.1_SQL.md`
18. **NoSQL as a sibling persistence object** — next shared chapter
19. Learn by building one small Book
20. The habits JX v0.1 wants to teach
21. Where to read next

The application sequence is:

```text
What is on the Page?
How does it look and line up?
What does it do?
Where does it run?
Where does durable relational data go?
Where does non-relational data go?
```

That maps to:

```text
Controls
   -> Style / Layout
      -> PASL / PASM
         -> Browser / Apache / native host
            -> SQL
               -> NoSQL
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
26. **Apache / web deployment and persistent JX hosting** — `JX_v0.1_Web_Hosting.md`
27. **SQL security, adapters, transactions, and Bag synchronization** — `JX_v0.1_SQL.md`
28. **NoSQL adapters and Bag synchronization** — next shared chapter
29. Rhetorical roles can become compiler evidence
30. Libraries should lower with the Book
31. Plugins
32. The gaps that matter most
33. Optimization priorities
34. What "native" should mean
35. The end product

For the engine reader, Style resolution belongs after the Control host contract because the renderer must resolve:

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

Then web deployment resolves the public/runtime boundary:

```text
Internet
   -> Apache
      -> persistent JX host or PHP-FPM adapter
         -> Book / Page / Bags / PASL
```

Then SQL resolves the durable relational-data boundary:

```text
Book / Bags
    -> SQL object
       -> secure adapter
          -> MySQL / MariaDB
          -> PostgreSQL-family
          -> SQLite3
          -> other PDO-backed adapter
```

SQL results come back through the JX boundary and can be committed into Bags; credentials and live database handles never cross into the browser.

NoSQL follows as its own sibling object rather than being disguised as SQL.

## Shared application mnemonic

> **Controls say what exists. Bags hold what changes. Collectors gather. Anchors place. Style paints and spaces. PASL makes it act. Apache guards the doorway. SQL persists relations. NoSQL persists its native model.**
