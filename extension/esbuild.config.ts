import * as esbuild from "esbuild";
import * as path from "path";

const entryPoints: Record<string, string> = {
  content: "./src/content.ts",
  background: "./src/background.ts",
  popup: "./src/popup.ts",
  options: "./src/options.ts",
};

const target = process.argv[2];
const watch = process.argv.includes("--watch");

if (!target || !entryPoints[target]) {
  console.error(`Unknown target: ${target}`);
  console.error(`Available targets: ${Object.keys(entryPoints).join(", ")}`);
  process.exit(1);
}

const config: esbuild.BuildOptions = {
  entryPoints: [entryPoints[target]],
  bundle: true,
  outfile: `${target}.js`,
  format: "iife",
  platform: "browser",
  target: "es2017",
  sourcemap: true,
  minify: false,
  alias: {
    "@shared": path.resolve(__dirname, "../shared/src"),
  },
};

if (watch) {
  esbuild
    .context(config)
    .then((ctx) => {
      ctx.watch();
      console.log(`Watching ${target}...`);
    })
    .catch((error) => {
      console.error(error);
      process.exit(1);
    });
} else {
  esbuild
    .build(config)
    .then(() => {
      console.log(`Built ${target}.js`);
    })
    .catch((error) => {
      console.error(error);
      process.exit(1);
    });
}
