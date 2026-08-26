# Hosting API — Book / Page / Bag

```jx
book = Book.load(path) | Book.compile(source)
page = Page.spawn(entry, bag?)
bag  = Bag.underwrite(size)
task = Task.underwrite(size)
task.push(key, value)
ref  = task.sign(node)
task.set(data).commit(ref)
remaining = task.quotient()
id = task.id()
```

Hosting module embeds PHP, isolates Books, owns server→browser protocol. PASM frames/segments are the current implementation substrate.
