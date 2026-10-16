#!/usr/bin/env python3
"""Convert a STEP file to a binary STL for the casting analyser.

Usage: step_to_stl.py in.step out.stl [tolerance_mm]
Needs: pip install cadquery
"""
import struct
import sys


def main():
    if len(sys.argv) < 3:
        print("usage: step_to_stl.py in.step out.stl [tolerance_mm]", file=sys.stderr)
        return 2
    try:
        import cadquery as cq
    except ImportError:
        print("cadquery is not installed", file=sys.stderr)
        return 3
    src, dst = sys.argv[1], sys.argv[2]
    tol = float(sys.argv[3]) if len(sys.argv) > 3 else 0.0
    shape = cq.importers.importStep(src).val()
    box = shape.BoundingBox()
    diag = (box.xlen ** 2 + box.ylen ** 2 + box.zlen ** 2) ** 0.5
    tol = tol or max(diag * 0.0005, 0.01)
    verts, tris = shape.tessellate(tol, 0.2)
    with open(dst, "wb") as f:
        f.write(b"step_to_stl".ljust(80, b"\0"))
        f.write(struct.pack("<I", len(tris)))
        for a, b, c in tris:
            pa, pb, pc = verts[a], verts[b], verts[c]
            f.write(struct.pack("<3f", 0.0, 0.0, 0.0))
            for p in (pa, pb, pc):
                f.write(struct.pack("<3f", p.x, p.y, p.z))
            f.write(b"\0\0")
    return 0


if __name__ == "__main__":
    sys.exit(main())
