# JX GLB Viewer

**Status:** ACTIVE browser tool on `feature/anatomy-modeler`.

The JX GLB Viewer is the inspection surface for binary glTF (`.glb`) output produced by JX tools such as the Anatomy Designer.

## Run

Start the JX project with PHP:

```bash
php -S localhost:8000
```

Open:

```text
http://localhost:8000/examples/jx-glb-viewer.html
```

The viewer can:

- open arbitrary `.glb` files from disk;
- drag/drop `.glb` files into the viewport;
- reopen the exact last GLB emitted by the JX Anatomy exporter;
- orbit, pan, and zoom;
- frame the model and select front, side, and top views;
- toggle grid, XYZ axes, and wireframe display;
- inspect mesh, vertex, triangle, material, texture, and animation counts;
- play animation clips contained in the GLB.

## Anatomy handoff

`host/browser/jx-anatomy-texture-skin.js` intercepts the successful response from `examples/anatomy-export-glb.php`, clones the binary response, and stores that exact GLB in `JXGLBStore` under the `last` key.

The Anatomy Designer then exposes **Open exported GLB in JX Viewer**. The viewer opens with:

```text
examples/jx-glb-viewer.html?source=last
```

This means the inspection step uses the exported GLB bytes, not a second reconstruction of the anatomy model.

## Architecture

```text
JX Anatomy descriptor
        |
        v
AnatomyGLB.php
        |
        v
actual GLB binary
     /       \
 download   JXGLBStore
                |
                v
         JX GLB Viewer
```

Model generation remains JX/PHP. Three.js and GLTFLoader are used only as the browser visualization layer for inspecting the resulting standard GLB.
