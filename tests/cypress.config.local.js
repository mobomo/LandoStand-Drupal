module.exports = {
  reporter: "junit",

  reporterOptions: {
    mochaFile: "test-results/test-output-[hash].xml",
  },

  chromeWebSecurity: false,
  defaultCommandTimeout: 10000,
  record: true,

  e2e: {
    setupNodeEvents(on, config) {
      // implement node event listeners here
    },
  },
};
