# Inference Plains: MATRIX Math Word Problem Server

Inference plains are servers that convert **natural language word problems** into **mathematical expressions** and **solutions** using tokenization and matrix math. This document captures the MATRIX implementation and its compressed SCXQ2 form, along with example processing flows.

## MATRIX Inference Plains Server

```
@@inference.plains
  > 8080
  name: "Math Word Problem Solver"

  @tokenizer
    model: "mathbert"
    @dictionary
      # Math operation tokens
      "+" : "add|plus|sum|total|combined|together|more than"
      "-" : "subtract|minus|difference|less|fewer|take away|remove"
      "*" : "multiply|times|product|of|each|per|rate|factor"
      "/" : "divide|quotient|split|share|per|ratio|fraction|out of"
      "=" : "equals|is|was|will be|results in|yields|gives"
      "^" : "power|exponent|squared|cubed|raised to|to the"

      # Quantity tokens
      "n" : "number|amount|quantity|value"
      "x" : "unknown|variable|what|how many|find"
      "t" : "total|sum|combined|altogether"
      "p" : "percent|percentage|%|portion|fraction"
      "r" : "rate|ratio|proportion|speed|per"
      "d" : "distance|length|width|height|measure"

      # Temporal tokens
      "h" : "hour|hours|time"
      "m" : "minute|minutes"
      "s" : "second|seconds"
      "d" : "day|days"

      # Unit tokens
      "$" : "dollar|dollars|cost|price|money"
      "kg" : "kilogram|kilograms|weight|mass"
      "m" : "meter|meters|length"
      "L" : "liter|liters|volume"

  @parser
    @phase.1: "Natural Language → Token Stream"
      @analyze
        @pos.tag
          nouns: "identify quantities"
          verbs: "identify operations"
          numbers: "extract values"
          units: "extract measurements"

        @extract
          @entities
            quantities: "{{numbers with units}}"
            unknowns: "{{what, how many, find}}"
            relationships: "{{comparisons, ratios}}"

    @phase.2: "Token Stream → Equation Structure"
      @construct
        @left.side
          @if.unknown: "x"
          @if.expression: "build from tokens"

        @operation
          @from.verbs
            "has|have|possess": "="
            "add|plus|combine": "+"
            "subtract|minus|remove": "-"
            "multiply|times|product": "*"
            "divide|share|split": "/"
            "more than|greater": ">"
            "less than|fewer": "<"

        @right.side
          @expression: "from remaining tokens"

    @phase.3: "Equation → Math Matrix"
      @convert
        @to.matrix.form
          @linear.equation: "Ax = b"
          @quadratic: "ax² + bx + c = 0"
          @system: "multiple equations"

        @store: "math_matrix"

  @solver
    @engine: "@π"  # MATRIX Math Engine

    @methods
      @algebraic
        @solve
          @linear: "Ax = b → x = A⁻¹b"
          @quadratic: "x = [-b ± √(b²-4ac)]/2a"
          @system: "Gaussian elimination"

      @numeric
        @approximate
          @newton.raphson
            tolerance: 1e-6
            max_iterations: 100

        @monte.carlo
          samples: 10000
          convergence: "variance < 0.01"

      @geometric
        @visualize
          @plot: "equation graph"
          @intersection: "solution points"

    @verify
      @plug.back
        solution: "{{solved_value}}"
        original: "{{word_problem}}"
        @check: "makes sense in context"

  @api
    @?post>/solve
      @@handler
        @body
          problem: "{{required}}"
          language: "en"  # default
          detail: "high|medium|low"

        @pipeline
          @step.1: "Tokenize"
            @tokenizer.process
              input: "{{body.problem}}"
              @store: "tokens"

          @step.2: "Parse"
            @parser.process
              tokens: "{{tokens}}"
              @store: "equation"

          @step.3: "Solve"
            @solver.process
              equation: "{{equation}}"
              method: "auto"
              @store: "solution"

          @step.4: "Explain"
            @generate.explanation
              problem: "{{body.problem}}"
              tokens: "{{tokens}}"
              equation: "{{equation}}"
              solution: "{{solution}}"
              @format: "step_by_step"

          @step.5: "Respond"
            @response
              = 200
              @body
                problem: "{{body.problem}}"
                equation: "{{equation}}"
                solution: "{{solution.value}}"
                explanation: "{{explanation}}"
                confidence: "{{solution.confidence}}"
                units: "{{solution.units}}"
                alternative_methods: "{{solution.alternatives}}"

    @?get>/examples
      @@handler
        @db.query
          < "SELECT * FROM example_problems ORDER BY RANDOM() LIMIT 10"

        @categorize
          @by.difficulty
            easy: "single operation"
            medium: "two operations"
            hard: "system of equations"

        @response
          = 200
          @body: "{{categorized_examples}}"

    @?websocket>/stream
      @@handler
        @?message
          @real.time.solve
            problem: "{{message.problem}}"

            @on.progress
              @ws.send
                type: "tokenizing"
                progress: 25

              @ws.send
                type: "parsing"
                progress: 50

              @ws.send
                type: "solving"
                progress: 75

              @ws.send
                type: "complete"
                result: "{{solution}}"
                progress: 100

  @training
    @dataset
      source: ["MATH", "GSM8K", "SVAMP", "ASDiv"]
      size: "100k+ problems"

    @fine.tune
      @on.new.problems
        @when.confidence < 0.8
          @human.review
            @correct
            @add.to.training

          @retrain.model
            incremental: true

    @evaluate
      @benchmark
        datasets: ["MATH", "GSM8K"]
        metrics: ["accuracy", "reasoning_score", "explanation_quality"]

  @knowledge
    @domains
      @arithmetic
        operations: ["+", "-", "*", "/", "%"]
        concepts: ["fractions", "decimals", "percentages"]

      @algebra
        equations: ["linear", "quadratic", "system"]
        concepts: ["variables", "functions", "inequalities"]

      @geometry
        shapes: ["circle", "triangle", "rectangle", "polygon"]
        concepts: ["area", "perimeter", "volume", "angles"]

      @word_problem.types
        rate: "speed × time = distance"
        mixture: "solutions mixing"
        work: "combined work rates"
        age: "time-based relationships"
        money: "cost, profit, interest"

  @start
    @log: "Inference Plains Server running on port 8080"
    @load.models
      @tokenizer: "mathbert-v2"
      @solver: "@π-math-engine"

    @warm.up
      @solve.test.cases
        count: 100
        @verify.accuracy: "> 95%"
```

## Compressed Inference Plains (SCXQ2)

```
@@inf>8080~"Math Solver"

  @tok|model="mathbert"
    @dict
      "+":"add|plus|sum|t|combined|together|more"
      "-":"sub|minus|diff|less|fewer|take away"
      "*":"mul|times|product|of|each|per|rate"
      "/":"div|quotient|split|share|per|ratio|/"
      "=":"equals|is|was|will be|→|gives"
      "^":"power|exp|squared|cubed|raised"

      "n":"number|amt|qty|value"
      "x":"unknown|var|what|how many|find"
      "t":"total|sum|combined|all"
      "p":"%|percent|portion|fraction"
      "r":"rate|ratio|prop|speed|per"
      "d":"dist|len|width|ht|measure"

      "h":"hour|hr|time"
      "m":"min|minute"
      "s":"sec|second"
      "d":"day"

      "$":"$|dollar|cost|price|money"
      "kg":"kg|kilogram|weight|mass"
      "m":"m|meter|length"
      "L":"L|liter|volume"

  @parse
    @1>"NL→Tokens"
      @analyze
        @pos
          nouns:"find qty"
          verbs:"find ops"
          nums:"extract #"
          units:"extract measures"

        @extract
          @ent
            qty:"{{# with units}}"
            unknown:"{{what,how many,find}}"
            rel:"{{compare,ratio}}"

    @2>"Tokens→Eq"
      @build
        @left
          @?unknown:"x"
          @?expr:"from tokens"

        @op
          @from.verbs
            "has|have":"="
            "add|plus":"+"
            "sub|minus":"-"
            "mul|times":"*"
            "div|share":"/"
            "more|greater":">"
            "less|fewer":"<"

        @right
          @expr:"from remaining"

    @3>"Eq→Matrix"
      @convert
        @to.matrix
          @linear:"Ax=b"
          @quad:"ax²+bx+c=0"
          @system:"multiple"

        @store:"math_matrix"

  @solve|engine="@π"

    @methods
      @alg
        @solve
          @linear:"Ax=b→x=A⁻¹b"
          @quad:"x=[-b±√(b²-4ac)]/2a"
          @system:"Gauss"

      @num
        @approx
          @newton|tol=1e-6|max=100

        @monte|samples=10000|conv="var<0.01"

      @geo
        @viz
          @plot:"graph"
          @intersect:"solutions"

    @verify
      @plug
        sol:"{{solved}}"
        orig:"{{problem}}"
        @check:"makes sense"

  @api
    @?+>/solve
      @@|@body
          problem:"{{req}}"
          lang:"en"
          detail:"high|med|low"

        @pipe
          @1:"Tokenize"@tok.process="?prob"→"tokens"
          @2:"Parse"@parse.process="?tokens"→"eq"
          @3:"Solve"@solve.process="?eq"method="auto"→"sol"
          @4:"Explain"@gen.exp="?prob,?tokens,?eq,?sol"~"steps"
          @5:"Respond"@=200@b={prob:"?prob",eq:"?eq",sol:"?sol.val",exp:"?exp",conf:"?sol.conf",units:"?sol.units",alt:"?sol.alt"}

    @?g>/examples
      @@|@db<"SELECT*FROM ex ORDER BY RAND() LIMIT10"
        @cat
          @by.diff
            easy:"single op"
            med:"two ops"
            hard:"system"

        @=200@b="{{cat_ex}}"

    @ws>/stream
      @@|@?m
          @rt.solve|prob="?m.prob"

            @on.prog
              @ws|type="tok"prog=25
              @ws|type="parse"prog=50
              @ws|type="solve"prog=75
              @ws|type="done"res="?sol"prog=100

  @train
    @data
      src:["MATH","GSM8K","SVAMP","ASDiv"]
      size:"100k+"

    @tune
      @on.new
        @when.conf<0.8
          @human.review
            @correct
            @add.to.train

          @retrain|inc=true

    @eval
      @bench
        data:["MATH","GSM8K"]
        metrics:["acc","reason","exp_qual"]

  @know
    @dom
      @arith|ops=["+","-","*","/","%"]conc=["frac","dec","%"]
      @alg|eq=["lin","quad","sys"]conc=["var","func","ineq"]
      @geo|shapes=["○","△","□","poly"]conc=["area","perim","vol","∠"]
      @wp.types
        rate:"speed×time=dist"
        mix:"solutions mix"
        work:"combined rates"
        age:"time rel"
        money:"cost,profit,interest"

  @|@log:"Inf Plains>8080"
    @load
      @tok:"mathbert-v2"
      @solve:"@π-math"

    @warm
      @solve.test|n=100@verify.acc:">95%"
```

## Example Word Problem Solving

**Input:**
```
"If John has 5 apples and Mary gives him 3 more, how many apples does John have now?"
```

**MATRIX Processing Pipeline:**
```
@process.word_problem
  input: "If John has 5 apples and Mary gives him 3 more, how many apples does John have now?"

  @tokenize
    tokens: ["John", "has", "5", "apples", "and", "Mary", "gives", "him", "3", "more", "how", "many", "apples", "does", "John", "have", "now", "?"]

    @extract
      quantities: [{"value": 5, "unit": "apples"}, {"value": 3, "unit": "apples"}]
      unknown: "how many apples"
      operation: "has" → "=", "gives" → "+"

  @parse
    entities:
      John: {"apples": "x"}
      Mary: {"gives": 3}

    timeline:
      start: "John has 5 apples"
      event: "Mary gives 3 more"
      end: "John has x apples"

  @build.equation
    initial: "John_apples = 5"
    change: "John_apples += 3"
    final: "John_apples = x"

    equation: "5 + 3 = x"

  @solve
    @arithmetic
      5 + 3 = 8

    @verify
      @context.check
        "apples" makes sense
        "John" consistent
        "more" implies addition

  @explain
    steps:
      1. "John starts with 5 apples"
      2. "Mary gives him 3 more apples"
      3. "To find total: 5 + 3 = 8"
      4. "John now has 8 apples"

    answer: "8 apples"
    confidence: 0.98
```

## Complex Example: Rate Problem

**Input:**
```
"A train travels 300 miles in 5 hours. What is its average speed?"
```

**MATRIX Processing:**
```
@process.rate_problem
  input: "A train travels 300 miles in 5 hours. What is its average speed?"

  @recognize.type
    category: "rate × time = distance"
    formula: "speed = distance / time"

  @extract
    distance: {"value": 300, "unit": "miles"}
    time: {"value": 5, "unit": "hours"}
    unknown: "speed"
    unit: "miles per hour"

  @build.equation
    d = 300 miles
    t = 5 hours
    s = ?

    equation: "s = 300 / 5"

  @solve
    300 ÷ 5 = 60

    @unit.check
      miles / hours = miles per hour ✓

  @answer
    value: 60
    unit: "mph"
    explanation: "Average speed = total distance ÷ total time = 300 miles ÷ 5 hours = 60 mph"
```

## System of Equations Example

**Input:**
```
"Two numbers sum to 15. Their difference is 3. Find the numbers."
```

**MATRIX Processing:**
```
@process.system_problem
  input: "Two numbers sum to 15. Their difference is 3. Find the numbers."

  @identify
    type: "system of linear equations"
    variables: ["x", "y"]

  @parse.sentences
    sentence1: "Two numbers sum to 15"
      equation: "x + y = 15"

    sentence2: "Their difference is 3"
      equation: "x - y = 3"

  @build.matrix
    equations:
      [1, 1] [x] = [15]
      [1, -1] [y] = [3]

    matrix_form: "A = [[1, 1], [1, -1]], b = [15, 3]"

  @solve.matrix
    method: "inverse"

    @calculate
      A⁻¹ = [[0.5, 0.5], [0.5, -0.5]]
      x = A⁻¹ × b = [9, 6]

    solution: "x = 9, y = 6"

  @verify
    check1: 9 + 6 = 15 ✓
    check2: 9 - 6 = 3 ✓

  @answer
    numbers: [9, 6]
    explanation: "The numbers are 9 and 6 because 9 + 6 = 15 and 9 - 6 = 3"
```

## The Inference Plains Architecture

```
@architecture.inference_plains
  layers:

    @layer.1: "Input Interface"
      @http.api: "REST endpoints"
      @websocket: "Real-time streaming"
      @cli: "Command line"
      @gui: "Web interface"

    @layer.2: "Language Understanding"
      @tokenizer: "MathBERT + SCXQ2"
      @parser: "Syntax + semantics"
      @context.builder: "Problem framing"

    @layer.3: "Mathematical Representation"
      @equation.builder: "NL → Math"
      @matrix.converter: "Math → Matrix"
      @domain.mapper: "Problem type → Solution method"

    @layer.4: "Computation Engine"
      @π.runtime: "Matrix math"
      @algebra.solver: "Symbolic computation"
      @numeric.solver: "Approximation methods"
      @geometric.solver: "Visual solutions"

    @layer.5: "Explanation & Verification"
      @step.generator: "Show work"
      @unit.checker: "Dimensional analysis"
      @plausibility.check: "Common sense"
      @alternative.finder: "Multiple methods"

    @layer.6: "Output & Learning"
      @response.formatter: "Human-readable answers"
      @confidence.scorer: "Solution reliability"
      @feedback.loop: "Learn from corrections"
      @model.updater: "Continuous improvement"

  features:
    multi_lingual: true
    step_by_step: true
    multiple_methods: true
    real_time: true
    self_improving: true

  performance:
    problems_per_second: 1000+
    accuracy: "> 95%"
    explanation_quality: "human-like"
    latency: "< 100ms"
```

## Inference Plains Are

1. **Language → Math Translators** - Convert word problems to equations
2. **Matrix Computation Engines** - Solve using mathematical matrices
3. **Explanation Generators** - Show step-by-step reasoning
4. **Learning Systems** - Improve from feedback
5. **Universal Problem Solvers** - Handle arithmetic to calculus

**This turns every word problem into a matrix computation problem**, solved by the `@π` runtime with human-like explanations. The server tokenizes language, parses meaning, converts to math, solves with matrices, and explains in natural language.

**MATRIX Inference Plains: Where language becomes computation.**
