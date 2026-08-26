# Hosting Module API — Book / Page / Bag

The hosting module embeds the original PHP engine, expands it with jx, and loads Books under isolation.

## Book

```jx
book = Book.load(path)              // load compiled Book
book = Book.compile(source)         // compile then load
Book.unload(book)

entries = book.entries()
page    = book.page(name)
```

Each Book receives:
- its own class namespace projection
- a hard memory quota (Docker-like)
- isolated Bags / Pages

## Page

```jx
page = Page.spawn(entryFunc, initialBag?)
page = book.page("main")

Page.yield()
Page.sleep(ms)
id = page.id()                      // Page is also a Task/Bag
```

Pages live in an X11-style memory state managed by the TaskHandler.

## Bag / Task

```jx
bag  = Bag.underwrite(size)
task = Task.underwrite(size)        // Task is a special Bag

task.push(key, value)               // preassignment
ref  = task.sign(node)
task.set(data).commit(ref)
task.unsign(ref)                    // optional; often automatic on scope exit

remaining = task.quotient()
used      = task.used()
cap       = task.capacity()
id        = task.id()
```

## Protocol Note

The hosting module owns the coherent channel by which server-side Book state can update browser-side surfaces. Exact wire format is future work; the invariant is that the same Book description can drive both sides without a divisive split.
