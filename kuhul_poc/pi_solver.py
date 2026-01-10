from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Dict, List, Tuple


@dataclass
class SolveResult:
    kind: str
    value: Any
    confidence: float
    verify: Dict[str, Any]
    steps: List[str]


def _is_close(a: float, b: float, tol: float = 1e-9) -> bool:
    return abs(a - b) <= tol * max(1.0, abs(a), abs(b))


def solve_scalar(expr: str, consts: Dict[str, float]) -> Tuple[float, List[str]]:
    allowed = set("0123456789.+-*/() c")
    for ch in expr:
        if ch not in allowed and not ch.isalpha():
            raise ValueError(f"illegal_char: {ch}")

    safe = expr
    for key, value in consts.items():
        safe = safe.replace(key, f"({value})")

    for ch in safe:
        if ch not in "0123456789.+-*/() ":
            raise ValueError(f"illegal_after_subst: {ch}")

    steps = [f"eval_scalar: {expr}", f"subst: {safe}"]
    val = eval(safe, {"__builtins__": {}}, {})
    return float(val), steps


def gaussian_elimination(A: List[List[float]], b: List[float]) -> Tuple[List[float], List[str]]:
    n = len(A)
    if n == 0 or any(len(row) != n for row in A) or len(b) != n:
        raise ValueError("shape_error")

    M = [row[:] + [b[i]] for i, row in enumerate(A)]
    steps = [f"gauss_jordan: n={n}"]

    for col in range(n):
        pivot_row = max(range(col, n), key=lambda r: abs(M[r][col]))
        if _is_close(M[pivot_row][col], 0.0):
            raise ValueError("singular_matrix")

        if pivot_row != col:
            M[col], M[pivot_row] = M[pivot_row], M[col]
            steps.append(f"swap r{col}<->r{pivot_row}")

        pivot = M[col][col]
        inv = 1.0 / pivot
        for j in range(col, n + 1):
            M[col][j] *= inv
        steps.append(f"normalize r{col} by 1/{pivot}")

        for row in range(n):
            if row == col:
                continue
            factor = M[row][col]
            if _is_close(factor, 0.0):
                continue
            for j in range(col, n + 1):
                M[row][j] -= factor * M[col][j]
            steps.append(f"eliminate r{row} using r{col} factor={factor}")

    x = [M[i][n] for i in range(n)]
    return x, steps


def verify_Ax_b(
    A: List[List[float]],
    x: List[float],
    b: List[float],
    tol: float = 1e-7,
) -> Dict[str, Any]:
    n = len(A)
    residuals = []
    ok = True
    for i in range(n):
        lhs = sum(A[i][j] * x[j] for j in range(n))
        res = lhs - b[i]
        residuals.append(res)
        if not _is_close(lhs, b[i], tol=tol):
            ok = False
    return {"ok": ok, "residuals": residuals, "tol": tol}


def pi_solve(matrix_spec: Dict[str, Any]) -> SolveResult:
    steps: List[str] = []
    kind = matrix_spec.get("kind")

    if kind == "scalar":
        expr = matrix_spec["eval"]["expr"]
        consts = matrix_spec["eval"].get("consts", {})
        val, s = solve_scalar(expr, consts)
        steps += s
        return SolveResult(
            kind="scalar",
            value=val,
            confidence=0.98,
            verify={"ok": True},
            steps=steps,
        )

    if kind == "Ax=b":
        A = matrix_spec["A"]
        b = matrix_spec["b"]
        vars_ = matrix_spec.get("x", [])
        xvec, s = gaussian_elimination(A, b)
        steps += s
        v = verify_Ax_b(A, xvec, b)
        conf = 0.99 if v["ok"] else 0.60
        out = {vars_[i] if i < len(vars_) else f"x{i}": xvec[i] for i in range(len(xvec))}
        return SolveResult(
            kind="Ax=b",
            value=out,
            confidence=conf,
            verify=v,
            steps=steps,
        )

    raise ValueError(f"unknown_matrix_kind: {kind}")


if __name__ == "__main__":
    matrix = {
        "kind": "Ax=b",
        "A": [[5.0]],
        "x": ["s"],
        "b": [300.0],
    }
    result = pi_solve(matrix)
    print(
        {
            "kind": result.kind,
            "value": result.value,
            "confidence": result.confidence,
            "verify": result.verify,
        }
    )
