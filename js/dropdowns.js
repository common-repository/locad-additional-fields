jQuery(function ($) {
  const BILLING = 'billing';
  const SHIPPING = 'shipping';

  // save municipalities from api response here
  const municipalities = {
    [SHIPPING]: {
      items: null,
      isLoading: false,
    },
    [BILLING]: {
      items: null,
      isLoading: false,
    },
  };

  const supportedCountries = ['PH'];

  const selectors = {
    [BILLING]: {
      defaultCountry: 'select[name="billing_country"]',
      defaultState: 'select[name="billing_state"]',
      defaultCity: 'input[name="billing_city"]',
      municipalities: 'select[name="custom_billing_municipalities"]',
      barangays: 'select[name="custom_billing_barangays"]',
      state: 'select[name="custom_billing_state"]',
      suburb: 'select[name="custom_billing_suburb"]',
    },
    [SHIPPING]: {
      defaultCountry: 'select[name="shipping_country"]',
      defaultState: 'select[name="shipping_state"]',
      defaultCity: 'input[name="shipping_city"]',
      municipalities: 'select[name="custom_shipping_municipalities"]',
      barangays: 'select[name="custom_shipping_barangays"]',
      state: 'select[name="custom_shipping_state"]',
      suburb: 'select[name="custom_shipping_suburb"]',
    },
  };

  const parentSelectors = {
    [BILLING]: {
      defaultCity: '#billing_city_field',
      municipalities: '.custom_billing_municipalities-select',
      barangays: '.custom_billing_barangays-select',
      suburb: '.custom_billing_suburb-select',
      state: '.custom_billing_state-select',
    },
    [SHIPPING]: {
      defaultCity: '#shipping_city_field',
      municipalities: '.custom_shipping_municipalities-select',
      barangays: '.custom_shipping_barangays-select',
      suburb: '.custom_shipping_suburb-select',
      state: '.custom_shipping_state-select',
    },
  };
  hideCustomInputs(BILLING);
  hideCustomInputs(SHIPPING);

  $('form.checkout').on('change', selectors[BILLING].defaultCountry, hideCustomInputs.bind(null, BILLING));
  $('form.checkout').on('change', selectors[BILLING].defaultState, showStateDependentInputs(BILLING));
  $('form.checkout').on('change', selectors[BILLING].municipalities, showMunicipalityDependentInputs(BILLING));
  $('form.checkout').on('change', selectors[BILLING].suburb, setSuburbValue(BILLING));

  $('form.checkout').on('change', selectors[SHIPPING].defaultCountry, hideCustomInputs.bind(null, SHIPPING));
  $('form.checkout').on('change', selectors[SHIPPING].defaultState, showStateDependentInputs(SHIPPING));
  $('form.checkout').on('change', selectors[SHIPPING].municipalities, showMunicipalityDependentInputs(SHIPPING));
  $('form.checkout').on('change', selectors[SHIPPING].suburb, setSuburbValue(SHIPPING));

  function getMunicipalities(type, country, state, callback) {
    municipalities[type].isLoading = true;
    $.get({
      // SITE_URL comes from php file (wp_localize_script)
      url: SITE_URL + `/wp-json/locad/${country}/${state}/${type}/municipalities`,
      success: function (data) {
        municipalities[type].isLoading = false;
        callback(data);
      },
      error: function () {
        municipalities[type].isLoading = false;
        showDefaultCityInput(type);
        hideCustomInputs(type);
      },
    });
  }

  function hideCustomInputs(type) {
    $(parentSelectors[type].barangays).hide();
    $(parentSelectors[type].suburb).hide();
    $(parentSelectors[type].municipalities).hide();
  }

  function hideDefaultCityInput(type) {
    setTimeout(function () {
      $(parentSelectors[type].defaultCity).hide();
    }, 10);
  }

  function showDefaultCityInput(type) {
    setTimeout(function () {
      $(selectors[type].defaultCity).val('');
      $(parentSelectors[type].defaultCity).show();
    }, 10);
  }

  function replaceOptions(select, options, placeholder) {
    select.empty();
    const optionsToRender = options.map(option => {
      return $('<option></option>').attr('value', option).text(option);
    });
    select.append(
      $('<option></option>').attr('value', '').attr('selected', true).attr('disabled', true).text(placeholder),
    );
    optionsToRender.forEach(opt => {
      select.append(opt);
    });
  }

  function showMunicipalityDependentInputs(type) {
    return function () {
      const selectedMunicipality = $(selectors[type].municipalities).val();

      const barangaysOptions = municipalities[type].items[selectedMunicipality];

      $(selectors[type].defaultCity).val(selectedMunicipality);

      $(parentSelectors[type].barangays).show();
      replaceOptions($(selectors[type].barangays), barangaysOptions, 'Select Barangay.');
    };
  }

  function showStateDependentInputs(type) {
    return function () {
      const selectedCountry = $(selectors[type].defaultCountry).val();
      const selectedState = $(selectors[type].defaultState).val();

      $(parentSelectors[type].municipalities).hide();
      $(parentSelectors[type].barangays).hide();
      if (
        selectedCountry &&
        selectedState &&
        !municipalities[type].isLoading &&
        supportedCountries.includes(selectedCountry)
      ) {
        getMunicipalities(type, selectedCountry, selectedState, function (data) {
          hideDefaultCityInput(type);
          municipalities[type].items = JSON.parse(data);
          const municipalityOptions = Object.keys(municipalities[type].items);
          if (municipalityOptions && municipalityOptions.length) {
            $(parentSelectors[type].municipalities).show();
            $(parentSelectors[type].barangays).hide();
            replaceOptions($(selectors[type].municipalities), municipalityOptions, 'Select municipality.');
          }
        });
      }
    };
  }

  function setSuburbValue(type) {
    return function () {
      $(selectors[type].defaultCity).val($(selectors[type].suburb).val());
    };
  }
});
