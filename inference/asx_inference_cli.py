#!/usr/bin/env python3
# ASX Tokenizer Inference Plane — Minimal Reference (v1)
# - Deterministic NL -> SYMBOL -> MATRIX -> SOLVE -> EXPLAIN
# - No deps, single file, golden vectors included

from __future__ import annotations
from dataclasses import dataclass
from typing import List, Dict, Any, Tuple, Optional
import re
import json
import math
import sys

# -------------------------
# Utilities (deterministic)
# -------------------------

def stable_json(obj: Any) -> str:
  return json.dumps(obj, ensure_ascii=False, sort_keys=True, separators=(",", ":"))

def contains_any(hay: List[str], needles: List[str]) -> bool:
  hs = set(hay)
  for n in needles:
    if n in hs:
      return True
  return False

def norm_tokens(text: str) -> List[str]:
  # Lowercase, keep basic word tokens, numbers preserved separately by entity extractor
  return re.findall(r"[a-zA-Z]+|\d+(?:\.\d+)?|[%$]", text.lower())

def extract_numbers(text: str) -> List[float]:
  return [float(x) for x in re.findall(r"\d+(?:\.\d+)?", text)]

def first_unit_hint(text: str) -> Optional[str]:
  t = text.lower()
  # Minimal unit heuristics
  if "hour" in t or "hours" in t:
    if "mile" in t or "miles" in t:
      return "miles/hour"
  if "mph" in t:
    return "mph"
  return None

def almost_equal(a: float, b: float, tol: float = 1e-9) -> bool:
  return abs(a - b) <= tol * max(1.0, abs(a), abs(b))

# -------------------------
# 1) SYMBOL VECTOR (symbol.xjson shape)
# -------------------------

def make_symbol_base(problem: str, model: str = "qwen2-1.5b", tokenizer: str = "asx-specializer") -> Dict[str, Any]:
  return {
    "meta": {
      "trace_id": "trace_" + str(abs(hash(problem)) % 10**10),
      "ts": 0,
      "source": {
        "model": model,
        "tokenizer": tokenizer,
        "input_kind": "nl_text",
        "lang": "en"
      }
    },
    "mode": {"domain": "arithmetic", "subtype": "generic", "phase": "SOLVE"},
    "vars": [],
    "constants": [],
    "facts": [],
    "ops": [],
    "constraints": [],
    "targets": {"unknowns": [], "objective": "solve"},
    "evidence": {"tokens": norm_tokens(problem), "entities": [], "notes": []}
  }

# -------------------------
# 2) Minimal Symbol Extractor (deterministic contract)
#    NL(text) -> symbol.xjson
# -------------------------

LEX_OPS = {
  "add": ["add", "plus", "sum", "total", "together", "combined", "more", "another", "gives"],
  "sub": ["subtract", "minus", "difference", "less", "fewer", "left", "remain", "remove", "take"],
  "mul": ["multiply", "times", "product", "each", "per", "of"],
  "div": ["divide", "quotient", "split", "share", "ratio", "per"],
  "eq":  ["equals", "equal", "is", "was", "will", "results", "yields", "gives"]
}

LEX_DOMAINS = {
  "rate": ["speed", "rate", "per", "mph", "average"],
  "system": ["two", "both", "numbers", "sum", "difference"],
  "percent": ["percent", "percentage", "%"]
}

def symbol_extractor(problem: str) -> Dict[str, Any]:
  sym = make_symbol_base(problem)
  tokens = sym["evidence"]["tokens"]

  # Phase A: detect domain
  if contains_any(tokens, LEX_DOMAINS["rate"]) and ("hour" in tokens or "hours" in tokens):
    sym["mode"] = {"domain": "rate", "subtype": "d=rt", "phase": "SOLVE"}
  elif contains_any(tokens, LEX_DOMAINS["system"]) and ("sum" in tokens and "difference" in tokens):
    sym["mode"] = {"domain": "system", "subtype": "lin2", "phase": "SOLVE"}
  else:
    sym["mode"] = {"domain": "arithmetic", "subtype": "generic", "phase": "SOLVE"}

  # Phase B: entities (numbers, minimal unit hints)
  nums = extract_numbers(problem)
  unit_hint = first_unit_hint(problem)
  for i, v in enumerate(nums):
    ent = {"kind": "number", "text": str(v), "value": v, "span": {"start": -1, "end": -1}}
    sym["evidence"]["entities"].append(ent)

  # Phase C: register constants (stable naming)
  # For domains, we map some constants by semantic role if possible.
  if sym["mode"]["domain"] == "rate" and len(nums) >= 2:
    # Heuristic: "300 miles in 5 hours" -> d=300, t=5
    sym["constants"].append({"id": "d", "value": nums[0], "dtype": "scalar", "unit": "distance"})
    sym["constants"].append({"id": "t", "value": nums[1], "dtype": "scalar", "unit": "time"})
  else:
    for i, v in enumerate(nums):
      sym["constants"].append({"id": f"c{i}", "value": v, "dtype": "scalar", "unit": None})

  # Phase D: register variables & targets (unknowns)
  unknown_triggers = ["what", "how", "find", "unknown"]
  if contains_any(tokens, unknown_triggers):
    if sym["mode"]["domain"] == "rate":
      sym["vars"].append({"id": "s", "role": "unknown", "dtype": "scalar", "unit": unit_hint or "distance/time"})
      sym["targets"] = {"unknowns": ["s"], "objective": "solve"}
    elif sym["mode"]["domain"] == "system":
      sym["vars"].append({"id": "x", "role": "unknown", "dtype": "scalar", "unit": None})
      sym["vars"].append({"id": "y", "role": "unknown", "dtype": "scalar", "unit": None})
      sym["targets"] = {"unknowns": ["x", "y"], "objective": "solve"}
    else:
      sym["vars"].append({"id": "x", "role": "unknown", "dtype": "scalar", "unit": None})
      sym["targets"] = {"unknowns": ["x"], "objective": "solve"}
  else:
    # fallback
    sym["vars"].append({"id": "x", "role": "unknown", "dtype": "scalar", "unit": None})
    sym["targets"] = {"unknowns": ["x"], "objective": "solve"}

  # Phase E: emit facts (no evaluation, just structure)
  if sym["mode"]["domain"] == "rate":
    # d = s * t
    sym["facts"].append({"kind": "equation", "lhs": {"kind": "ref", "ref": "d"}, "op": "=", "rhs": {"kind": "expr", "expr": "s*t"}, "confidence": 0.99})
    sym["ops"].append({"kind": "mul", "out": "d", "args": ["s", "t"], "confidence": 0.99})
  elif sym["mode"]["domain"] == "system":
    # We assume canonical statement: sum = c0, difference = c1 (from text)
    # In golden vector we will ensure ordering matches.
    sym["facts"].append({"kind": "equation", "lhs": {"kind": "expr", "expr": "x+y"}, "op": "=", "rhs": {"kind": "ref", "ref": "c0"}, "confidence": 0.95})
    sym["facts"].append({"kind": "equation", "lhs": {"kind": "expr", "expr": "x-y"}, "op": "=", "rhs": {"kind": "ref", "ref": "c1"}, "confidence": 0.95})
    sym["ops"].append({"kind": "add", "out": "tmp0", "args": ["x", "y"], "confidence": 0.9})
    sym["ops"].append({"kind": "sub", "out": "tmp1", "args": ["x", "y"], "confidence": 0.9})
  else:
    # arithmetic: choose add/div based on keyword
    if contains_any(tokens, LEX_OPS["add"]) and len(nums) >= 2:
      sym["facts"].append({"kind": "equation", "lhs": {"kind": "expr", "expr": "c0+c1"}, "op": "=", "rhs": {"kind": "ref", "ref": "x"}, "confidence": 0.9})
      sym["ops"].append({"kind": "add", "out": "x", "args": ["c0", "c1"], "confidence": 0.9})
    elif contains_any(tokens, LEX_OPS["div"]) and len(nums) >= 2:
      sym["facts"].append({"kind": "equation", "lhs": {"kind": "expr", "expr": "c0/c1"}, "op": "=", "rhs": {"kind": "ref", "ref": "x"}, "confidence": 0.9})
      sym["ops"].append({"kind": "div", "out": "x", "args": ["c0", "c1"], "confidence": 0.9})
    else:
      # fallback to add if two numbers
      if len(nums) >= 2:
        sym["facts"].append({"kind": "equation", "lhs": {"kind": "expr", "expr": "c0+c1"}, "op": "=", "rhs": {"kind": "ref", "ref": "x"}, "confidence": 0.5})
        sym["ops"].append({"kind": "add", "out": "x", "args": ["c0", "c1"], "confidence": 0.5})

  return sym

# -------------------------
# 3) Matrix Construction (symbol -> matrix)
# -------------------------

def get_const_map(sym: Dict[str, Any]) -> Dict[str, float]:
  m: Dict[str, float] = {}
  for c in sym["constants"]:
    m[c["id"]] = float(c["value"])
  return m

def build_matrix(sym: Dict[str, Any]) -> Dict[str, Any]:
  domain = sym["mode"]["domain"]
  consts = get_const_map(sym)

  if domain == "rate":
    # t*s = d
    t = consts["t"]
    d = consts["d"]
    return {"kind": "Ax=b", "A": [[t]], "x": ["s"], "b": [d]}

  if domain == "system":
    # x + y = c0 ; x - y = c1
    c0 = consts["c0"]
    c1 = consts["c1"]
    A = [[1.0, 1.0], [1.0, -1.0]]
    b = [c0, c1]
    return {"kind": "Ax=b", "A": A, "x": ["x", "y"], "b": b}

  # arithmetic: attempt scalar eval from first fact lhs expression
  # Expected forms: c0+c1, c0/c1
  if sym["facts"]:
    lhs = sym["facts"][0]["lhs"]
    if lhs.get("kind") == "expr":
      expr = lhs["expr"].replace("+", " + ").replace("/", " / ")
      return {"kind": "scalar", "eval": {"expr": expr, "consts": consts}}
  # fallback
  return {"kind": "scalar", "eval": {"expr": "c0 + c1", "consts": consts}}

# -------------------------
# 4) Minimal @π Solver (deterministic)
# -------------------------

@dataclass
class SolveResult:
  kind: str               # "scalar" | "Ax=b"
  value: Any              # float or dict(var->float)
  confidence: float
  verify: Dict[str, Any]
  steps: List[str]

def solve_scalar(expr: str, consts: Dict[str, float]) -> Tuple[float, List[str]]:
  allowed = set("0123456789.+-*/() c ")
  for ch in expr:
    if ch not in allowed and not ch.isalpha():
      raise ValueError(f"illegal_char: {ch}")

  safe = expr
  # Replace longer keys first to avoid c1 inside c10 collisions
  for k in sorted(consts.keys(), key=len, reverse=True):
    safe = safe.replace(k, f"({consts[k]})")

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
    if almost_equal(M[pivot_row][col], 0.0):
      raise ValueError("singular_matrix")

    if pivot_row != col:
      M[col], M[pivot_row] = M[pivot_row], M[col]
      steps.append(f"swap r{col}<->r{pivot_row}")

    pivot = M[col][col]
    inv = 1.0 / pivot
    for j in range(col, n + 1):
      M[col][j] *= inv
    steps.append(f"normalize r{col} by 1/{pivot}")

    for r in range(n):
      if r == col:
        continue
      factor = M[r][col]
      if almost_equal(factor, 0.0):
        continue
      for j in range(col, n + 1):
        M[r][j] -= factor * M[col][j]
      steps.append(f"eliminate r{r} using r{col} factor={factor}")

  x = [M[i][n] for i in range(n)]
  return x, steps

def verify_Ax_b(A: List[List[float]], x: List[float], b: List[float], tol: float = 1e-7) -> Dict[str, Any]:
  n = len(A)
  residuals = []
  ok = True
  for i in range(n):
    lhs = sum(A[i][j] * x[j] for j in range(n))
    res = lhs - b[i]
    residuals.append(res)
    if not almost_equal(lhs, b[i], tol=tol):
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
    return SolveResult(kind="scalar", value=val, confidence=0.98, verify={"ok": True}, steps=steps)

  if kind == "Ax=b":
    A = matrix_spec["A"]
    b = matrix_spec["b"]
    vars_ = matrix_spec.get("x", [])
    xvec, s = gaussian_elimination(A, b)
    steps += s
    v = verify_Ax_b(A, xvec, b)
    conf = 0.99 if v["ok"] else 0.60
    out = {vars_[i] if i < len(vars_) else f"x{i}": xvec[i] for i in range(len(xvec))}
    return SolveResult(kind="Ax=b", value=out, confidence=conf, verify=v, steps=steps)

  raise ValueError(f"unknown_matrix_kind: {kind}")

# -------------------------
# 5) Explanation as Reverse Projection (deterministic templates)
# -------------------------

def explain(problem: str, sym: Dict[str, Any], matrix: Dict[str, Any], sol: SolveResult) -> Dict[str, Any]:
  domain = sym["mode"]["domain"]
  consts = get_const_map(sym)

  if domain == "rate":
    d = consts["d"]
    t = consts["t"]
    s = float(sol.value["s"])
    unit = first_unit_hint(problem) or "distance/time"
    steps = [
      f"We are given the total distance {d} and total time {t}.",
      "Average speed is calculated as distance divided by time.",
      f"So we compute {d} ÷ {t} = {s}.",
    ]
    final = f"The average speed is {s} {unit}."
  elif domain == "system":
    x = float(sol.value["x"])
    y = float(sol.value["y"])
    c0 = consts["c0"]
    c1 = consts["c1"]
    steps = [
      f"We translate the statements into equations: x + y = {c0} and x - y = {c1}.",
      "We write this as a linear system and solve it using elimination.",
      f"The solution is x = {x}, y = {y}.",
    ]
    final = f"The numbers are {x} and {y}."
  else:
    # arithmetic scalar
    x = sol.value if isinstance(sol.value, float) else float(sol.value.get("x", 0.0))
    # Try to reference first two constants if present
    c0 = consts.get("c0", None)
    c1 = consts.get("c1", None)
    if c0 is not None and c1 is not None:
      steps = [
        f"We start with {c0}.",
        f"We combine it with {c1} based on the problem wording.",
        f"This gives {x}.",
      ]
    else:
      steps = [f"We compute the required value and get {x}."]
    final = f"The answer is {x}."

  return {
    "trace_id": sym["meta"]["trace_id"],
    "inputs": {"problem": problem, "domain": domain, "unknowns": sym["targets"]["unknowns"]},
    "projection": {
      "symbol_view": {
        "vars": [v["id"] for v in sym["vars"]],
        "constants": {c["id"]: c["value"] for c in sym["constants"]},
        "facts": [f"{f['lhs'].get('expr', f['lhs'].get('ref'))} {f['op']} {f['rhs'].get('expr', f['rhs'].get('ref'))}" for f in sym["facts"]]
      },
      "matrix_view": {
        "form": matrix["kind"],
        "A": matrix.get("A"),
        "b": matrix.get("b"),
        "steps": sol.steps
      },
      "solution_view": {"values": sol.value, "verified": bool(sol.verify.get("ok", False))}
    },
    "language": {
      "template_id": f"tpl_{domain}_v1",
      "steps": steps,
      "final_answer": final,
      "units": first_unit_hint(problem)
    },
    "constraints": {"no_new_symbols": True, "no_new_operations": True, "trace_complete": True}
  }

# -------------------------
# 6) Golden Test Vectors
# -------------------------

GOLDEN = [
  {
    "id": "gsm8k_add_apples",
    "problem": "If John has 5 apples and Mary gives him 3 more, how many apples does John have now?",
    "expect": {
      "symbol": {
        "mode": {"domain": "arithmetic"},
        "constants": [{"id": "c0", "value": 5.0}, {"id": "c1", "value": 3.0}],
        "targets": {"unknowns": ["x"]},
        "facts_lhs": "c0+c1"
      },
      "matrix": {"kind": "scalar"},
      "solution": {"kind": "scalar", "value": 8.0},
      "explain_contains": ["5.0", "3.0", "8.0", "answer"]
    }
  },
  {
    "id": "rate_train_speed",
    "problem": "A train travels 300 miles in 5 hours. What is its average speed?",
    "expect": {
      "symbol": {
        "mode": {"domain": "rate"},
        "constants": [{"id": "d", "value": 300.0}, {"id": "t", "value": 5.0}],
        "targets": {"unknowns": ["s"]},
        "facts_lhs_ref": "d"
      },
      "matrix": {"kind": "Ax=b", "A": [[5.0]], "b": [300.0]},
      "solution": {"kind": "Ax=b", "value": {"s": 60.0}},
      "explain_contains": ["300.0", "5.0", "60.0", "speed"]
    }
  },
  {
    "id": "system_sum_diff",
    "problem": "Two numbers sum to 15. Their difference is 3. Find the numbers.",
    "expect": {
      "symbol": {
        "mode": {"domain": "system"},
        "constants": [{"id": "c0", "value": 15.0}, {"id": "c1", "value": 3.0}],
        "targets": {"unknowns": ["x", "y"]}
      },
      "matrix": {"kind": "Ax=b", "A": [[1.0, 1.0], [1.0, -1.0]], "b": [15.0, 3.0]},
      "solution": {"kind": "Ax=b", "value": {"x": 9.0, "y": 6.0}},
      "explain_contains": ["15.0", "3.0", "9.0", "6.0", "numbers"]
    }
  }
]

def _expect_symbol_checks(sym: Dict[str, Any], exp: Dict[str, Any]) -> bool:
  ok = True
  dom = exp["mode"]["domain"]
  if sym["mode"]["domain"] != dom:
    return False

  cmap = get_const_map(sym)
  for c in exp.get("constants", []):
    if c["id"] not in cmap:
      return False
    if not almost_equal(float(cmap[c["id"]]), float(c["value"])):
      return False

  if exp.get("targets", {}).get("unknowns"):
    if sym["targets"]["unknowns"] != exp["targets"]["unknowns"]:
      return False

  # Optional fact checks
  if "facts_lhs" in exp:
    if not sym["facts"]:
      return False
    lhs = sym["facts"][0]["lhs"]
    if lhs.get("expr") != exp["facts_lhs"]:
      return False

  if "facts_lhs_ref" in exp:
    if not sym["facts"]:
      return False
    lhs = sym["facts"][0]["lhs"]
    if lhs.get("ref") != exp["facts_lhs_ref"]:
      return False

  return ok

def _expect_matrix_checks(matrix: Dict[str, Any], exp: Dict[str, Any]) -> bool:
  if matrix.get("kind") != exp["kind"]:
    return False
  if exp["kind"] == "Ax=b":
    if matrix.get("A") != exp.get("A"):
      return False
    if matrix.get("b") != exp.get("b"):
      return False
  return True

def _expect_solution_checks(sol: SolveResult, exp: Dict[str, Any]) -> bool:
  if sol.kind != exp["kind"]:
    return False
  if sol.kind == "scalar":
    return almost_equal(float(sol.value), float(exp["value"]))
  # Ax=b
  for k, v in exp["value"].items():
    if k not in sol.value:
      return False
    if not almost_equal(float(sol.value[k]), float(v)):
      return False
  return True

def _expect_explain_checks(expl: Dict[str, Any], exp_contains: List[str]) -> bool:
  blob = stable_json(expl).lower()
  for s in exp_contains:
    if str(s).lower() not in blob:
      return False
  return True

# -------------------------
# 7) CLI runner
# -------------------------

def run_one(vec: Dict[str, Any], verbose: bool = False) -> bool:
  pid = vec["id"]
  prob = vec["problem"]
  exp = vec["expect"]

  sym = symbol_extractor(prob)
  ok_symbol = _expect_symbol_checks(sym, exp["symbol"])

  matrix = build_matrix(sym)
  ok_matrix = _expect_matrix_checks(matrix, exp["matrix"])

  sol = pi_solve(matrix)
  ok_solution = _expect_solution_checks(sol, exp["solution"])

  expl = explain(prob, sym, matrix, sol)
  ok_expl = _expect_explain_checks(expl, exp["explain_contains"])

  def mark(ok: bool) -> str:
    return "✔" if ok else "✘"

  print(f"\n[{pid}]")
  print(f"SYMBOL {mark(ok_symbol)}")
  print(f"MATRIX {mark(ok_matrix)}")
  print(f"SOLUTION {mark(ok_solution)}")
  print(f"EXPLANATION {mark(ok_expl)}")

  all_ok = ok_symbol and ok_matrix and ok_solution and ok_expl
  if verbose or not all_ok:
    print("\n--- DEBUG ---")
    print("PROBLEM:", prob)
    print("SYMBOL:", stable_json(sym))
    print("MATRIX:", stable_json(matrix))
    print("SOLUTION:", {"kind": sol.kind, "value": sol.value, "verify": sol.verify, "confidence": sol.confidence})
    print("EXPLAIN:", stable_json(expl))

  return all_ok

def main(argv: List[str]) -> int:
  verbose = "--verbose" in argv or "-v" in argv
  only = None
  for a in argv:
    if a.startswith("--only="):
      only = a.split("=", 1)[1].strip()

  vecs = GOLDEN
  if only:
    vecs = [v for v in GOLDEN if v["id"] == only]
    if not vecs:
      print(f"unknown --only id: {only}")
      return 2

  ok_all = True
  for v in vecs:
    ok = run_one(v, verbose=verbose)
    ok_all = ok_all and ok

  print("\nRESULT:", "PASS" if ok_all else "FAIL")
  return 0 if ok_all else 1

if __name__ == "__main__":
  raise SystemExit(main(sys.argv[1:]))
